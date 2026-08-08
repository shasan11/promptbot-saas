import { Switch as HeadlessSwitch } from '@headlessui/react';

export default function Switch({ checked, onChange, label, description, disabled = false }) {
    return (
        <HeadlessSwitch.Group as="div" className="flex items-start justify-between gap-4">
            {(label || description) && (
                <span className="flex flex-col">
                    {label && <HeadlessSwitch.Label as="span" className="text-sm font-medium text-slate-700">{label}</HeadlessSwitch.Label>}
                    {description && <HeadlessSwitch.Description as="span" className="text-xs text-slate-500">{description}</HeadlessSwitch.Description>}
                </span>
            )}
            <HeadlessSwitch
                checked={checked}
                onChange={onChange}
                disabled={disabled}
                className={`${checked ? 'bg-brand-600' : 'bg-slate-200'} relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50`}
            >
                <span className={`${checked ? 'translate-x-5' : 'translate-x-1'} inline-block h-4 w-4 transform rounded-full bg-white transition`} />
            </HeadlessSwitch>
        </HeadlessSwitch.Group>
    );
}
