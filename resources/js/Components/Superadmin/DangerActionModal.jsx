import { useState } from 'react';

export default function DangerActionModal({ open, title, confirmation, processing, onCancel, onConfirm }) {
    const [value, setValue] = useState('');

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 px-4">
            <div className="w-full max-w-lg rounded-lg bg-white p-6 shadow-2xl">
                <div className="mb-5">
                    <div className="text-lg font-bold text-slate-950">{title}</div>
                    <p className="mt-2 text-sm text-slate-500">
                        Type <span className="font-semibold text-slate-900">{confirmation}</span> to continue.
                    </p>
                </div>
                <input
                    className="w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                />
                <div className="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        disabled={value !== confirmation || processing}
                        onClick={onConfirm}
                        className="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing ? 'Confirming...' : 'Confirm'}
                    </button>
                </div>
            </div>
        </div>
    );
}
