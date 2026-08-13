import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import { AlertTriangle } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function DangerConfirmDialog({
    open,
    title,
    consequence,
    reversible = false,
    affected,
    confirmation,
    reasonRequired = false,
    confirmLabel = 'Confirm',
    processing = false,
    onConfirm,
    onCancel,
}) {
    const [value, setValue] = useState('');
    const [reason, setReason] = useState('');

    useEffect(() => {
        if (open) {
            setValue('');
            setReason('');
        }
    }, [open]);

    if (!open) {
        return null;
    }

    return (
        <Modal
            open={open}
            onClose={onCancel}
            size="sm"
            title={(
                <span className="flex items-center gap-2 text-rose-700">
                    <AlertTriangle className="h-4 w-4" /> {title}
                </span>
            )}
            footer={(
                <>
                    <Button variant="secondary" onClick={onCancel}>Cancel</Button>
                    <Button
                        variant="danger"
                        onClick={() => onConfirm(reason)}
                        loading={processing}
                        disabled={(confirmation ? value !== confirmation : false) || (reasonRequired && !reason.trim())}
                    >
                        {confirmLabel}
                    </Button>
                </>
            )}
        >
            <div className="space-y-3 text-sm text-slate-600">
                {consequence && <p>{consequence}</p>}
                {affected && (
                    <p className="rounded-md bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">{affected}</p>
                )}
                <p className="font-medium text-rose-700">{reversible ? 'This action can be undone.' : 'This action cannot be undone.'}</p>
                {confirmation && (
                    <div>
                        <p className="mb-1.5 text-xs text-slate-500">
                            Type <span className="font-semibold text-slate-900">{confirmation}</span> to confirm.
                        </p>
                        <Input value={value} onChange={(event) => setValue(event.target.value)} autoComplete="off" />
                    </div>
                )}
                {reasonRequired && (
                    <div>
                        <p className="mb-1.5 text-xs text-slate-500">Reason (stored in the audit log)</p>
                        <Input value={reason} onChange={(event) => setReason(event.target.value)} autoComplete="off" />
                    </div>
                )}
            </div>
        </Modal>
    );
}
