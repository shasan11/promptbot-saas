import { Dialog, Transition } from '@headlessui/react';
import { X } from 'lucide-react';
import { Fragment } from 'react';

export default function Drawer({ open, onClose, title, description, children, footer, width = 'max-w-md' }) {
    return (
        <Transition show={open} as={Fragment}>
            <Dialog onClose={onClose} className="relative z-50">
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-150" enterFrom="opacity-0" enterTo="opacity-100"
                    leave="ease-in duration-100" leaveFrom="opacity-100" leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-navy-950/60" aria-hidden="true" />
                </Transition.Child>

                <div className="fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <Transition.Child
                        as={Fragment}
                        enter="transform transition duration-150" enterFrom="translate-x-full" enterTo="translate-x-0"
                        leave="transform transition duration-100" leaveFrom="translate-x-0" leaveTo="translate-x-full"
                    >
                        <Dialog.Panel className={`flex h-full w-screen ${width} flex-col bg-white shadow-soft-lg`}>
                            <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
                                <div>
                                    {title && <Dialog.Title className="text-base font-semibold text-slate-900">{title}</Dialog.Title>}
                                    {description && <Dialog.Description className="mt-1 text-sm text-slate-500">{description}</Dialog.Description>}
                                </div>
                                <button
                                    type="button"
                                    onClick={onClose}
                                    aria-label="Close panel"
                                    className="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                            <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>
                            {footer && <div className="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">{footer}</div>}
                        </Dialog.Panel>
                    </Transition.Child>
                </div>
            </Dialog>
        </Transition>
    );
}
