import { usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function BrandLogo({ onDark = false, className = 'h-8 w-auto' }) {
    const platform = usePage().props.platform || {};
    const candidates = onDark
        ? [platform.logoDarkUrl, platform.logoUrl, '/branding/dark_logo.png', '/branding/logo/dark_logo.png']
        : [platform.logoUrl, '/branding/light_logo.png', '/branding/logo/light_logo.png'];
    const available = candidates.filter(Boolean);
    const [index, setIndex] = useState(0);
    return <img src={available[Math.min(index, available.length - 1)]} onError={() => setIndex(value => Math.min(value + 1, available.length - 1))} alt={platform.name || 'PromptBot'} className={`${className} object-contain`} />;
}
