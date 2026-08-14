import HorizontalWorkspaceShell from '@/Components/Tenant/HorizontalWorkspaceShell';
import { Globe2, MessagesSquare } from 'lucide-react';

const items = [
    { label: 'Channels', route: 'tenant.admin.channels.index', pattern: 'tenant.admin.channels.*', permission: 'channels.view', icon: MessagesSquare },
    { label: 'Experience', route: 'tenant.admin.experience.index', pattern: 'tenant.admin.experience.*', permission: 'experience.view', icon: Globe2 },
];

export default function EngagementShell({ secondarySections = [], ...props }) {
    return <HorizontalWorkspaceShell workspace="AI & Engagement" items={items} sections={secondarySections} {...props} />;
}
