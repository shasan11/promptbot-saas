import AIShell from '@/Components/AI/AIShell';
import Alert from '@/Components/UI/Alert';
import Button from '@/Components/UI/Button';
import { SectionCard } from '@/Components/UI/Card';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { FlaskConical, ImagePlus, Square } from 'lucide-react';
import { useRef, useState } from 'react';

function parseEvent(block) {
    const lines = block.split('\n');
    const event = lines.find((line) => line.startsWith('event:'))?.slice(6).trim();
    const raw = lines.filter((line) => line.startsWith('data:')).map((line) => line.slice(5).trim()).join('\n');
    if (!event || !raw) return null;
    try { return { event, data: JSON.parse(raw) }; } catch { return null; }
}

export default function Playground({ agents, result: initialResult, runtimeError }) {
    const [agentUuid, setAgentUuid] = useState(agents[0]?.public_uuid || '');
    const [message, setMessage] = useState('');
    const [images, setImages] = useState([]);
    const [result, setResult] = useState(initialResult);
    const [output, setOutput] = useState(initialResult?.text || '');
    const [error, setError] = useState(runtimeError || '');
    const [status, setStatus] = useState('');
    const [running, setRunning] = useState(false);
    const streamId = useRef(null);

    const handleEvent = ({ event, data }) => {
        if (event === 'started') setStatus('Generating…');
        if (event === 'text') setOutput((current) => current + (data.content || ''));
        if (event === 'status') setStatus(data.message || 'Working…');
        if (event === 'completed') { setResult(data); setOutput(data.text || ''); setStatus('Ready'); }
        if (event === 'cancelled') setStatus('Cancelled');
        if (event === 'failed') { setError(data.message || 'The AI run failed safely.'); setStatus('Failed'); }
    };

    const run = async (event) => {
        event.preventDefault();
        if (!agentUuid || !message.trim() || running) return;
        const id = crypto.randomUUID(); streamId.current = id;
        setRunning(true); setOutput(''); setResult(null); setError(''); setStatus('Connecting…');
        const body = new FormData();
        body.append('agent_uuid', agentUuid); body.append('message', message); body.append('stream_id', id);
        images.forEach((image) => body.append('images[]', image));
        try {
            const token = decodeURIComponent(document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='))?.split('=')[1] || '');
            const response = await fetch(route('tenant.admin.ai.playground.stream'), {
                method: 'POST', body, credentials: 'same-origin',
                headers: { Accept: 'text/event-stream', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': token },
            });
            if (!response.ok || !response.body) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Unable to start the AI stream.');
            }
            const reader = response.body.getReader(); const decoder = new TextDecoder(); let buffer = '';
            while (true) {
                const { value, done } = await reader.read();
                buffer += decoder.decode(value || new Uint8Array(), { stream: !done }).replaceAll('\r\n', '\n');
                const blocks = buffer.split('\n\n'); buffer = blocks.pop() || '';
                blocks.map(parseEvent).filter(Boolean).forEach(handleEvent);
                if (done) break;
            }
            if (buffer.trim()) { const parsed = parseEvent(buffer); if (parsed) handleEvent(parsed); }
        } catch (exception) {
            setError(exception.message || 'The AI stream ended unexpectedly.'); setStatus('Failed');
        } finally {
            setRunning(false); streamId.current = null;
        }
    };

    const cancel = async () => {
        if (!streamId.current) return;
        setStatus('Cancelling…');
        await window.axios.post(route('tenant.admin.ai.playground.cancel'), { stream_id: streamId.current }).catch(() => {});
    };

    return <AIShell title="Agent playground" description="Stream a real tenant agent response. Inputs and image contents are not stored verbatim in run logs.">
        <div className="grid gap-6 xl:grid-cols-2">
            <SectionCard title="Test input" description="Provider charges may apply.">
                <form className="space-y-4" onSubmit={run}>
                    <label className="text-sm font-medium text-slate-700">Agent<Select className="mt-1" value={agentUuid} onChange={(event) => setAgentUuid(event.target.value)} disabled={running}>{agents.map((agent) => <option key={agent.public_uuid} value={agent.public_uuid}>{agent.name} · {agent.status} · {agent.provider || 'no provider'}</option>)}</Select></label>
                    <label className="text-sm font-medium text-slate-700">Message<Textarea className="mt-1 min-h-40" value={message} onChange={(event) => setMessage(event.target.value)} placeholder="Ask a question that should be answered from your permitted knowledge…" disabled={running} required /></label>
                    <label className="block rounded-md border border-dashed border-slate-300 p-3 text-sm text-slate-600">
                        <span className="flex items-center gap-2 font-medium"><ImagePlus className="h-4 w-4" />Images (optional)</span>
                        <input className="mt-2 block w-full text-xs" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple disabled={running} onChange={(event) => setImages(Array.from(event.target.files || []).slice(0, 4))} />
                        <span className="mt-1 block text-xs text-slate-400">Up to four images, 5 MB each. Requires a multimodal provider.</span>
                    </label>
                    <div className="flex gap-2"><Button type="submit" icon={FlaskConical} loading={running} disabled={!agents.length || running}>Run agent</Button>{running && <Button type="button" variant="secondary" icon={Square} onClick={cancel}>Cancel</Button>}</div>
                </form>
            </SectionCard>
            <SectionCard title="Result" description="Generated output is persisted only after completion and is never sent automatically from the playground.">
                {error && <Alert tone="danger">{error}</Alert>}
                {status && <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">{status}</p>}
                {output ? <div><div className="whitespace-pre-wrap text-sm leading-6 text-slate-700">{output}</div>{result?.citations?.length > 0 && <div className="mt-5 border-t border-slate-100 pt-4"><p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Sources</p><ol className="mt-2 space-y-2 text-sm text-slate-600">{result.citations.map((citation, index) => <li key={`${citation.url || citation.document_title}-${index}`}>[{index + 1}] {citation.document_title || citation.url || 'Workspace source'}{citation.page ? `, page ${citation.page}` : ''}</li>)}</ol></div>}{result?.run_uuid && <p className="mt-4 text-xs text-slate-400">Run {result.run_uuid} · {result.latency_ms} ms</p>}</div> : !error && <p className="text-sm text-slate-500">Choose an agent and send a test message. Text will arrive as Neuron produces it.</p>}
            </SectionCard>
        </div>
    </AIShell>;
}
