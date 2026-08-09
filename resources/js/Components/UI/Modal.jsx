import { Dialog, Transition } from '@headlessui/react';
import { X } from 'lucide-react';
import { Fragment } from 'react';

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};

export default function Modal({
    open,
    show,
    onClose,
    title,
    description,
    size = 'md',
    maxWidth,
    children,
    footer,
}) {
    const isOpen = open ?? show ?? false;
    const modalSize = maxWidth ?? size;

    return (
        <Transition show={isOpen} as={Fragment}>
            <Dialog onClose={onClose} className="relative z-50">
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-150" enterFrom="opacity-0" enterTo="opacity-100"
                    leave="ease-in duration-100" leaveFrom="opacity-100" leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-navy-950/60" aria-hidden="true" />
                </Transition.Child>

                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <Transition.Child
                        as={Fragment}
                        enter="ease-out duration-150" enterFrom="opacity-0 scale-95" enterTo="opacity-100 scale-100"
                        leave="ease-in duration-100" leaveFrom="opacity-100 scale-100" leaveTo="opacity-0 scale-95"
                    >
                        <Dialog.Panel className={`w-full ${widths[modalSize] || widths.md} rounded-lg bg-white shadow-soft-lg`}>
                            {(title || description) && (
                                <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                                    <div>
                                        {title && <Dialog.Title className="text-base font-semibold text-slate-900">{title}</Dialog.Title>}
                                        {description && <Dialog.Description className="mt-1 text-sm text-slate-500">{description}</Dialog.Description>}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        aria-label="Close dialog"
                                        className="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            )}
                            <div className={title || description ? 'max-h-[70vh] overflow-y-auto px-6 py-5' : 'max-h-[70vh] overflow-y-auto'}>{children}</div>
                            {footer && <div className="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">{footer}</div>}
                        </Dialog.Panel>
                    </Transition.Child>
                </div>
            </Dialog>
        </Transition>
    );
}
