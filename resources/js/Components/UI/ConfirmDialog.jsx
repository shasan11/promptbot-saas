import Button from '@/Components/UI/Button';
import Modal from '@/Components/UI/Modal';

export default function ConfirmDialog({
    open,
    title,
    children,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    variant = 'primary',
    processing = false,
    onConfirm,
    onCancel,
}) {
    return (
        <Modal
            open={open}
            onClose={onCancel}
            title={title}
            size="sm"
            footer={(
                <>
                    <Button variant="secondary" onClick={onCancel}>{cancelLabel}</Button>
                    <Button variant={variant} onClick={onConfirm} loading={processing}>{confirmLabel}</Button>
                </>
            )}
        >
            <div className="text-sm text-slate-600">{children}</div>
        </Modal>
    );
}
