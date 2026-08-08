import Badge from '@/Components/UI/Badge';

const statusTones = {
    active: 'brand',
    draft: 'neutral',
    connecting: 'info',
    disabled: 'neutral',
    needs_attention: 'warning',
    authentication_required: 'danger',
    degraded: 'warning',
    rate_limited: 'warning',
    error: 'danger',
    disconnected: 'danger',
    archived: 'neutral',
};

const healthTones = {
    healthy: 'brand',
    degraded: 'warning',
    needs_attention: 'warning',
    authentication_expired: 'danger',
    rate_limited: 'warning',
    disconnected: 'danger',
    disabled: 'neutral',
    error: 'danger',
    unknown: 'neutral',
};

export function humanize(value) {
    if (!value) return 'Unknown';
    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function StatusBadge({ value }) {
    return <Badge tone={statusTones[value] || 'neutral'}>{humanize(value)}</Badge>;
}

export function HealthBadge({ value }) {
    return <Badge tone={healthTones[value] || 'neutral'}>{humanize(value)}</Badge>;
}

export function CapabilityList({ capabilities = [], limit = 4 }) {
    const visible = capabilities.slice(0, limit);
    const remaining = Math.max(0, capabilities.length - visible.length);

    return (
        <div className="flex flex-wrap gap-1">
            {visible.map((capability) => <Badge key={capability} tone="neutral">{humanize(capability.split('.').pop())}</Badge>)}
            {remaining > 0 && <Badge tone="info">+{remaining}</Badge>}
        </div>
    );
}
