import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import { ImageOff, Upload } from 'lucide-react';
import { useRef } from 'react';

export function ColorField({ value, disabled, onChange }) {
    const hex = /^#[0-9A-Fa-f]{6}$/.test(value ?? '') ? value : '#000000';

    return (
        <div className="mt-2 flex items-center gap-3">
            <input type="color" disabled={disabled} value={hex} onChange={(event) => onChange(event.target.value)} className="h-10 w-12 shrink-0 cursor-pointer rounded-md border border-slate-300 p-1 disabled:cursor-not-allowed" aria-label="Pick color" />
            <Input type="text" disabled={disabled} value={value ?? ''} placeholder="#0F172A" maxLength={7} onChange={(event) => onChange(event.target.value)} className="font-mono uppercase" />
        </div>
    );
}

export function ImageField({ field, file, removeChecked, disabled, onFile, onRemoveToggle }) {
    const inputRef = useRef(null);
    const previewUrl = file ? URL.createObjectURL(file) : (!removeChecked ? field.currentImageUrl : null);
    const hasCurrentImage = Boolean(field.currentImageUrl);

    return (
        <div className="mt-2">
            <div className="flex items-center gap-4">
                <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50">
                    {previewUrl ? <img src={previewUrl} alt="" className="h-full w-full object-contain" /> : <ImageOff className="h-5 w-5 text-slate-300" strokeWidth={1.5} />}
                </div>
                <div className="flex-1">
                    <input ref={inputRef} type="file" accept={field.accept || 'image/*'} disabled={disabled} onChange={(event) => onFile(event.target.files?.[0] || null)} className="hidden" />
                    <Button type="button" variant="secondary" size="sm" icon={Upload} disabled={disabled} onClick={() => inputRef.current?.click()}>
                        {file ? file.name : 'Choose image'}
                    </Button>
                    {hasCurrentImage && (
                        <label className="mt-2 flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input type="checkbox" disabled={disabled || Boolean(file)} checked={removeChecked} onChange={(event) => onRemoveToggle(event.target.checked)} className="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                            Remove current image
                        </label>
                    )}
                </div>
            </div>
        </div>
    );
}

export function FieldControl({ field, value, disabled, onChange }) {
    if (field.type === 'select') {
        return (
            <Select disabled={disabled} value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
                <option value="">—</option>
                {field.options.map((option) => {
                    const optValue = Array.isArray(option) ? option[0] : option;
                    const optLabel = Array.isArray(option) ? option[1] : option;
                    return <option key={optValue} value={optValue}>{optLabel}</option>;
                })}
            </Select>
        );
    }

    if (field.type === 'textarea') {
        return <Textarea disabled={disabled} value={value ?? ''} placeholder={field.placeholder || ''} onChange={(event) => onChange(event.target.value)} />;
    }

    if (field.type === 'color') {
        return <ColorField value={value} disabled={disabled} onChange={onChange} />;
    }

    const htmlType = ['email', 'url', 'password', 'number'].includes(field.type) ? field.type : 'text';

    return (
        <Input
            disabled={disabled}
            type={htmlType}
            value={value ?? ''}
            placeholder={field.sensitive && field.configured ? 'Configured — leave blank to keep current value' : field.placeholder || ''}
            autoComplete={field.sensitive ? 'new-password' : undefined}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}
