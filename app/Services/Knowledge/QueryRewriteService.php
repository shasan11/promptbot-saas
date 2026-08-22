<?php

namespace App\Services\Knowledge;

use App\Models\Inbox\Conversation;
use Illuminate\Support\Str;

/**
 * Makes a follow-up question searchable on its own.
 *
 * Retrieval embeds whatever the customer just typed. That works for a first
 * question and fails completely for the second one:
 *
 *   "Tell me about the Enterprise plan."   → retrieves Enterprise material
 *   "How much does it cost?"               → embeds six words with no subject
 *
 * The second query has no lexical or semantic overlap with the pricing
 * material the customer is actually asking about, so it scores near zero and
 * — with the similarity threshold applied — returns nothing at all.
 *
 * Deliberately heuristic-first. Sending every message to an LLM to be
 * rewritten would add a full round trip and a per-message bill to the hot
 * path of every conversation, for a problem that only affects short,
 * referential follow-ups. This detects that specific shape and augments the
 * query with the topic already established in the thread, at zero cost and
 * sub-millisecond latency.
 */
class QueryRewriteService
{
    /**
     * Words that point at something said earlier instead of naming it. Their
     * presence in a short message is the strongest cheap signal that the
     * message cannot be understood standalone.
     */
    private const REFERENTIAL = ['it', 'its', 'it\'s', 'that', 'this', 'they', 'them', 'those', 'these', 'one', 'ones', 'there', 'he', 'she', 'his', 'her'];

    /** Openers that routinely start an elliptical follow-up ("how much?", "what about refunds?"). */
    private const ELLIPTICAL_OPENERS = ['how much', 'how many', 'how long', 'what about', 'how about', 'and what', 'and how', 'why not', 'what if', 'can i', 'is it', 'does it', 'do they', 'when does', 'where does'];

    /**
     * Includes conversational filler verbs ("tell", "want", "know") as well
     * as grammatical stop words: they survive a naive length filter and would
     * otherwise be weighted as topic terms, diluting the real subject in the
     * rewritten query.
     */
    private const STOP_WORDS = ['the', 'and', 'for', 'are', 'can', 'you', 'our', 'how', 'what', 'when', 'does', 'with', 'from', 'that', 'this', 'your', 'have', 'has', 'was', 'were', 'will', 'would', 'could', 'should', 'about', 'there', 'their', 'they', 'them', 'been', 'being', 'into', 'more', 'much', 'many', 'some', 'any', 'all', 'not', 'but', 'his', 'her', 'she', 'him', 'who', 'why', 'get', 'got', 'let', 'may', 'now', 'one', 'out', 'own', 'per', 'too', 'use', 'via', 'yes', 'yet',
        'tell', 'know', 'want', 'need', 'like', 'make', 'take', 'give', 'come', 'look', 'find', 'help', 'show', 'says', 'said', 'please', 'thanks', 'thank', 'hello', 'also', 'just', 'really', 'still', 'even', 'back', 'than', 'then', 'well', 'very', 'here', 'over', 'only', 'other', 'anything', 'something', 'me', 'my', 'we', 'us'];

    /**
     * Returns the text retrieval should actually search for.
     *
     * Never destructive: the customer's own words are always preserved and the
     * established topic is prepended. A wrong guess therefore degrades to a
     * slightly noisier query rather than searching for the wrong thing.
     */
    public function rewrite(string $message, ?Conversation $conversation): string
    {
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? '');

        if ($message === '' || ! $conversation || ! $this->needsContext($message)) {
            return $message;
        }

        $topic = $this->establishedTopic($conversation);

        return $topic === '' ? $message : $topic.' '.$message;
    }

    /**
     * True when the message cannot stand on its own as a search query.
     *
     * Both conditions are required to be reasonably confident: a long message
     * containing "it" usually still carries its own subject, and a short
     * message with a concrete noun ("refund policy?") searches fine as-is.
     */
    public function needsContext(string $message): bool
    {
        $lower = Str::lower(trim($message));
        $words = preg_split('/\W+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        // A message this long almost always names its own subject.
        if ($wordCount === 0 || $wordCount > 9) {
            return false;
        }

        foreach (self::ELLIPTICAL_OPENERS as $opener) {
            if (str_starts_with($lower, $opener)) {
                return true;
            }
        }

        if (array_intersect($words, self::REFERENTIAL) !== []) {
            return true;
        }

        // Very short fragments ("cheaper option?", "and refunds") are almost
        // always continuations of the previous turn.
        return $wordCount <= 3;
    }

    /**
     * The subject the conversation has already established.
     *
     * Pulled from the most recent turns, customer messages weighted ahead of
     * bot replies — what the customer asked about is a better description of
     * their intent than the wording the assistant happened to answer with,
     * and bot replies are long enough to swamp the query if given equal say.
     */
    private function establishedTopic(Conversation $conversation, int $maxTerms = 6): string
    {
        $recent = $conversation->messages()
            ->whereIn('direction', ['inbound', 'outbound'])
            ->latest('id')
            // Skips the message currently being answered, which is already in
            // the query and would otherwise just duplicate its own terms.
            ->limit(5)
            ->get(['body', 'direction'])
            ->slice(1);

        if ($recent->isEmpty()) {
            return '';
        }

        $weights = [];

        foreach ($recent as $index => $message) {
            // Recency and speaker both matter: the previous turn describes the
            // topic better than four turns ago, and the customer's own words
            // better than the assistant's.
            $weight = ($message->direction === 'inbound' ? 2.0 : 1.0) / (1 + $index);

            foreach ($this->terms((string) $message->body) as $term) {
                $weights[$term] = ($weights[$term] ?? 0) + $weight;
            }
        }

        arsort($weights);

        return implode(' ', array_slice(array_keys($weights), 0, $maxTerms));
    }

    /** @return array<int, string> */
    private function terms(string $text): array
    {
        // Bot replies can be long; only their opening carries the topic, and
        // reading all of it would let boilerplate dominate the weighting.
        $text = Str::limit(strip_tags($text), 400, '');
        $words = preg_split('/\W+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) > 3
                && ! in_array($word, self::STOP_WORDS, true)
                && ! is_numeric($word),
        )));
    }
}
