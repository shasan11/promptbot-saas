const STAGES = [
    { value: 'uploaded', label: 'Uploaded' },
    { value: 'validating', label: 'Validating' },
    { value: 'scanning', label: 'Scanning' },
    { value: 'extracting', label: 'Extracting text' },
    { value: 'normalizing', label: 'Normalising' },
    { value: 'detecting_language', label: 'Detecting language' },
    { value: 'deduplicating', label: 'Checking duplicates' },
    { value: 'chunking', label: 'Chunking' },
    { value: 'embedding', label: 'Generating embeddings' },
    { value: 'indexing', label: 'Indexing' },
    { value: 'ready', label: 'Ready' },
];

/**
 * Stage-by-stage progress for one item.
 *
 * Shows where processing actually is rather than a spinner, because "stuck at
 * embedding for six minutes" and "stuck at extraction for six minutes" mean
 * completely different things to whoever has to fix it.
 */
export default function ProcessingProgress({ stage, status, progress, failureStage, compact = false }) {
    const currentIndex = STAGES.findIndex((s) => s.value === (failureStage || stage));
    const failed = status === 'failed';
    const percentage = typeof progress === 'number'
        ? progress
        : Math.round(((currentIndex + 1) / STAGES.length) * 100);

    if (compact) {
        return (
            <div className="min-w-[8rem]">
                <div
                    className="h-1.5 w-full overflow-hidden rounded-full bg-slate-200"
                    role="progressbar"
                    aria-valuenow={failed ? 0 : percentage}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={`Processing progress: ${STAGES[currentIndex]?.label || 'unknown stage'}`}
                >
                    <div
                        className={`h-full rounded-full transition-[width] duration-500 ${failed ? 'bg-rose-500' : 'bg-brand-500'}`}
                        style={{ width: `${failed ? 100 : Math.max(4, percentage)}%` }}
                    />
                </div>
                <p className="mt-1 text-[11px] text-slate-500">
                    {failed ? `Failed at ${STAGES[currentIndex]?.label || 'processing'}` : STAGES[currentIndex]?.label || 'Waiting'}
                </p>
            </div>
        );
    }

    return (
        <ol className="space-y-1.5">
            {STAGES.map((item, index) => {
                const isDone = !failed && index < currentIndex;
                const isCurrent = index === currentIndex;
                const isFailure = failed && isCurrent;

                return (
                    <li key={item.value} className="flex items-center gap-2.5 text-sm">
                        <span
                            aria-hidden="true"
                            className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-[10px] font-bold ${
                                isFailure ? 'border-rose-300 bg-rose-100 text-rose-700'
                                    : isDone ? 'border-emerald-300 bg-emerald-100 text-emerald-700'
                                        : isCurrent ? 'border-brand-300 bg-brand-100 text-brand-700'
                                            : 'border-slate-200 bg-white text-slate-300'
                            }`}
                        >
                            {isFailure ? '!' : isDone ? '✓' : index + 1}
                        </span>
                        <span className={
                            isFailure ? 'font-medium text-rose-700'
                                : isCurrent ? 'font-medium text-slate-900'
                                    : isDone ? 'text-slate-600' : 'text-slate-400'
                        }>
                            {item.label}
                        </span>
                        {isFailure && <span className="text-xs text-rose-600">— failed here</span>}
                    </li>
                );
            })}
        </ol>
    );
}
