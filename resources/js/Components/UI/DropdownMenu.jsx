import { Menu, Transition } from '@headlessui/react';
import { MoreHorizontal } from 'lucide-react';
import { Fragment } from 'react';

export default function DropdownMenu({ trigger, items = [], align = 'right' }) {
    return (
        <Menu as="div" className="relative inline-block text-left">
            <Menu.Button
                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-800"
                aria-label="Open actions menu"
            >
                {trigger || <MoreHorizontal className="h-4 w-4" />}
            </Menu.Button>
            <Transition
                as={Fragment}
                enter="transition ease-out duration-100" enterFrom="transform opacity-0 scale-95" enterTo="transform opacity-100 scale-100"
                leave="transition ease-in duration-75" leaveFrom="transform opacity-100 scale-100" leaveTo="transform opacity-0 scale-95"
            >
                <Menu.Items className={`absolute z-20 mt-1 w-52 rounded-md border border-slate-200 bg-white p-1 shadow-soft-lg focus:outline-none ${align === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left'}`}>
                    {items.map((item, index) => (
                        item.divider ? (
                            <div key={`divider-${index}`} className="my-1 h-px bg-slate-100" />
                        ) : (
                            <Menu.Item key={item.label}>
                                {({ active }) => (
                                    <button
                                        type="button"
                                        onClick={item.onClick}
                                        disabled={item.disabled}
                                        className={`flex w-full items-center gap-2 rounded px-3 py-2 text-left text-sm ${
                                            item.danger ? 'text-rose-600' : 'text-slate-700'
                                        } ${active ? (item.danger ? 'bg-rose-50' : 'bg-slate-50') : ''} disabled:cursor-not-allowed disabled:opacity-50`}
                                    >
                                        {item.icon && <item.icon className="h-4 w-4" aria-hidden="true" />}
                                        {item.label}
                                    </button>
                                )}
                            </Menu.Item>
                        )
                    ))}
                </Menu.Items>
            </Transition>
        </Menu>
    );
}
