<?php

namespace Tests\Unit\Inbox;

use App\Models\Channel\Channel;
use App\Models\Customer\Contact;
use App\Models\Inbox\Conversation;
use Tests\TestCase;

/**
 * `recipientAddress()` is the single source of truth for where an outbound
 * reply goes. It replaced an `email ?: phone` chain that silently returned
 * null on four of the seven channel types — and a null recipient does not
 * fail loudly, it just means nobody ever answers the customer.
 *
 * Built entirely from unsaved models with relations set by hand: the method
 * is pure branching over the channel type and the contact's identifiers, and
 * proving that needs no database.
 */
class ConversationRecipientAddressTest extends TestCase
{
    private function conversation(?string $channelType, ?array $contact): Conversation
    {
        $conversation = new Conversation;
        $conversation->setRelation('channel', $channelType === null ? null : new Channel(['type' => $channelType]));
        $conversation->setRelation('contact', $contact === null ? null : tap(new Contact($contact), function (Contact $model) use ($contact): void {
            if (isset($contact['id'])) {
                $model->id = $contact['id'];
            }
        }));

        return $conversation;
    }

    public function test_email_uses_the_contact_email(): void
    {
        $this->assertSame(
            'buyer@example.test',
            $this->conversation('email', ['email' => 'buyer@example.test', 'phone' => '+15550000'])->recipientAddress(),
        );
    }

    /**
     * Web chat has no routable address at all — delivery happens by the widget
     * polling the messages table. A visitor who was never asked for an email
     * has neither an email nor a phone, so the old chain produced null and
     * `AutonomousReplyService::send()`'s `recipient_available` gate silently
     * refused to reply. The sentinel exists so that gate reflects reality.
     */
    public function test_web_chat_resolves_to_a_stable_sentinel_even_with_no_email_or_phone(): void
    {
        $address = $this->conversation('web_chat', ['id' => 42])->recipientAddress();

        $this->assertSame('web_chat:42', $address);
        $this->assertNotNull($address);
    }

    public function test_platform_channels_address_by_external_id(): void
    {
        // Messenger, Instagram and Telegram identify a person by a
        // platform-scoped id (PSID / IGSID / chat id), which is exactly what
        // the old email-first chain ignored.
        foreach (['messenger', 'instagram', 'telegram'] as $type) {
            $this->assertSame(
                'psid-9001',
                $this->conversation($type, ['external_id' => 'psid-9001', 'email' => 'ignored@example.test'])->recipientAddress(),
                "{$type} did not address by external id.",
            );
        }
    }

    public function test_phone_channels_prefer_the_phone_and_fall_back_to_external_id(): void
    {
        foreach (['whatsapp', 'sms'] as $type) {
            $this->assertSame(
                '+15551234567',
                $this->conversation($type, ['phone' => '+15551234567', 'external_id' => 'wa-1'])->recipientAddress(),
            );

            $this->assertSame(
                'wa-1',
                $this->conversation($type, ['phone' => null, 'external_id' => 'wa-1'])->recipientAddress(),
            );
        }
    }

    public function test_a_conversation_without_a_contact_has_no_recipient(): void
    {
        $this->assertNull($this->conversation('email', null)->recipientAddress());
    }

    public function test_an_unknown_channel_type_falls_back_to_email_then_phone(): void
    {
        $this->assertSame('fallback@example.test', $this->conversation('carrier_pigeon', ['email' => 'fallback@example.test', 'phone' => '+1'])->recipientAddress());
        $this->assertSame('+15550001', $this->conversation('carrier_pigeon', ['email' => null, 'phone' => '+15550001'])->recipientAddress());
        $this->assertNull($this->conversation(null, ['email' => null, 'phone' => null])->recipientAddress());
    }

    /**
     * The regression itself: an email-first chain returns null for every
     * channel that does not carry an email, and null is what stopped the AI
     * replying at all. No supported channel type may produce null for a
     * contact that carries its own channel's identifier.
     */
    public function test_no_supported_channel_returns_null_for_a_fully_identified_contact(): void
    {
        $contact = ['id' => 7, 'email' => null, 'phone' => '+15550002', 'external_id' => 'ext-7'];

        foreach (['web_chat', 'messenger', 'instagram', 'telegram', 'whatsapp', 'sms'] as $type) {
            $this->assertNotNull(
                $this->conversation($type, $contact)->recipientAddress(),
                "{$type} produced a null recipient — nothing would be sent.",
            );
        }
    }
}
