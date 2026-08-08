import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';

const tones = {
    info: { icon: Info, classes: 'border-blue-200 bg-blue-50 text-blue-800' },
    success: { icon: CheckCircle2, classes: 'border-brand-200 bg-brand-50 text-brand-800' },
    warning: { icon: AlertTriangle, classes: 'border-amber-200 bg-amber-50 text-amber-800' },
    danger: { icon: XCircle, classes: 'border-rose-200 bg-rose-50 text-rose-800' },
};

export default function Alert({ tone = 'info', title, children, className = '' }) {
    const { icon: Icon, classes } = tones[tone] || tones.info;

    return (
        <div role={tone === 'danger' ? 'alert' : 'status'} className={`flex gap-3 rounded-md border p-4 text-sm ${classes} ${className}`}>
            <Icon className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <div>
                {title && <p className="font-semibold">{title}</p>}
                {children && <div className={title ? 'mt-1 opacity-90' : ''}>{children}</div>}
            </div>
        </div>
    );
}
