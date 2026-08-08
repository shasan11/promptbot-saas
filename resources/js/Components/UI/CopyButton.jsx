import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

export default function CopyButton({ value, label = 'Copy' }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard unavailable — no-op
        }
    };

    return (
        <button
            type="button"
            onClick={copy}
            aria-label={copied ? 'Copied' : label}
            className="inline-flex h-7 w-7 items-center justify-center rounded text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800"
        >
            {copied ? <Check className="h-3.5 w-3.5 text-brand-600" /> : <Copy className="h-3.5 w-3.5" />}
        </button>
    );
}
