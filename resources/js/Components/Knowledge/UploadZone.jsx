import { FileUp, Loader2, X } from 'lucide-react';
import { useRef, useState } from 'react';

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

/**
 * Drag-and-drop upload with a visible per-file queue.
 *
 * Files are held here until submit so the user can review and remove them.
 * Oversized files are rejected client-side with a reason attached to the file
 * itself — the server enforces the same limit authoritatively, but making the
 * user wait for a 50 MB upload to learn it was too big is poor manners.
 */
export default function UploadZone({
    files,
    onChange,
    maxFileSizeKb = 51200,
    maxFiles = 25,
    uploading = false,
    progress = 0,
    accept = '.pdf,.doc,.docx,.txt,.md,.csv,.xls,.xlsx,.ppt,.pptx,.html,.json,.xml',
}) {
    const inputRef = useRef(null);
    const [dragging, setDragging] = useState(false);
    const [rejected, setRejected] = useState([]);

    const addFiles = (incoming) => {
        const accepted = [];
        const refused = [];

        Array.from(incoming).forEach((file) => {
            if (file.size > maxFileSizeKb * 1024) {
                refused.push({ name: file.name, reason: `Larger than ${formatBytes(maxFileSizeKb * 1024)}` });
                return;
            }

            // Same content, same name, already queued — silently ignore rather
            // than uploading it twice.
            if (files.some((existing) => existing.name === file.name && existing.size === file.size)) {
                return;
            }

            accepted.push(file);
        });

        const room = maxFiles - files.length;

        if (accepted.length > room) {
            accepted.slice(room).forEach((file) => refused.push({ name: file.name, reason: `Over the ${maxFiles}-file limit for one upload` }));
        }

        setRejected(refused);
        onChange([...files, ...accepted.slice(0, Math.max(0, room))]);
    };

    return (
        <div>
            <div
                onDragOver={(event) => { event.preventDefault(); setDragging(true); }}
                onDragLeave={() => setDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);
                    addFiles(event.dataTransfer.files);
                }}
                className={`rounded-lg border-2 border-dashed p-8 text-center transition-colors ${
                    dragging ? 'border-brand-400 bg-brand-50' : 'border-slate-300 bg-slate-50/60'
                }`}
            >
                <FileUp className="mx-auto h-8 w-8 text-slate-400" aria-hidden="true" />
                <p className="mt-3 text-sm font-medium text-slate-700">Drag files here, or</p>
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    className="mt-2 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800"
                >
                    Choose files
                </button>
                <p className="mt-3 text-xs text-slate-500">
                    PDF, Word, Excel, PowerPoint, text, Markdown, CSV, HTML and JSON.
                    Up to {formatBytes(maxFileSizeKb * 1024)} each, {maxFiles} files at a time.
                </p>
                <input
                    ref={inputRef}
                    type="file"
                    multiple
                    accept={accept}
                    className="sr-only"
                    aria-label="Choose documents to upload"
                    onChange={(event) => {
                        addFiles(event.target.files);
                        // Reset so re-selecting the same file still fires onChange.
                        event.target.value = '';
                    }}
                />
            </div>

            {uploading && (
                <div className="mt-4">
                    <div className="flex items-center justify-between text-xs font-medium text-slate-600">
                        <span className="flex items-center gap-1.5">
                            <Loader2 className="h-3.5 w-3.5 animate-spin motion-reduce:animate-none" aria-hidden="true" />
                            Uploading…
                        </span>
                        <span>{progress}%</span>
                    </div>
                    <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-valuenow={progress} aria-valuemin={0} aria-valuemax={100}>
                        <div className="h-full rounded-full bg-brand-500 transition-[width]" style={{ width: `${progress}%` }} />
                    </div>
                </div>
            )}

            {files.length > 0 && (
                <ul className="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                    {files.map((file, index) => (
                        <li key={`${file.name}-${index}`} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                            <span className="min-w-0 flex-1 truncate text-slate-700">{file.name}</span>
                            <span className="shrink-0 text-xs text-slate-400">{formatBytes(file.size)}</span>
                            {!uploading && (
                                <button
                                    type="button"
                                    onClick={() => onChange(files.filter((_, i) => i !== index))}
                                    aria-label={`Remove ${file.name}`}
                                    className="shrink-0 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {rejected.length > 0 && (
                <ul className="mt-3 space-y-1" role="alert">
                    {rejected.map((file) => (
                        <li key={file.name} className="text-xs text-rose-600">
                            <span className="font-medium">{file.name}</span> was not added — {file.reason}.
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
