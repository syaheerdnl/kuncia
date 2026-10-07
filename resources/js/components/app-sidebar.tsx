import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    FileText,
    Home,
    LayoutGrid,
    LineChart,
    Users,
    Wrench,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, Role } from '@/types';

const dashboardItem: NavItem = {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
};

const navByRole: Record<Role, NavItem[]> = {
    landlord: [
        dashboardItem,
        { title: 'Properties', href: '/properties', icon: Building2 },
        { title: 'Tenants', href: '/tenants', icon: Users },
        { title: 'Invoices', href: '/invoices', icon: FileText },
        { title: 'Maintenance', href: '/maintenance', icon: Wrench },
        { title: 'Reports', href: '/reports', icon: LineChart },
    ],
    tenant: [
        dashboardItem,
        { title: 'My Unit', href: '/my/unit', icon: Home },
        { title: 'My Invoices', href: '/my/invoices', icon: FileText },
        { title: 'My Requests', href: '/my/maintenance', icon: Wrench },
    ],
    maintenance: [
        dashboardItem,
        { title: 'My Tasks', href: '/tasks', icon: ClipboardList },
    ],
};

export function AppSidebar() {
    const { auth } = usePage().props;
    const items = auth.role ? navByRole[auth.role] : [dashboardItem];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
