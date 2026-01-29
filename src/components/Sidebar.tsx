import {
  LayoutDashboard,
  Ticket,
  TicketCheck,
  Calendar,
  Users,
  Settings,
  UserPlus,
  UserRound,
  LogOut,
  X,
  type LucideIcon,
} from "lucide-react";
import { NavLink } from "@/components/NavLink";
import { cn } from "@/lib/utils";
import { useAuth } from "@/hooks/useAuth";
import { normalizeRoleSlug, type RoleSlug } from "@/lib/permissions";
import { useIsMobile } from "@/hooks/use-mobile";

type NavigationItem = {
  name: string;
  href: string;
  icon: LucideIcon;
  allowedRoles?: RoleSlug[];
};

const navigation: NavigationItem[] = [
  {
    name: "Dashboard",
    href: "/",
    icon: LayoutDashboard,
    allowedRoles: ["admin"],
  },
  {
    name: "Entradas",
    href: "/entradas",
    icon: Ticket,
    allowedRoles: ["admin", "vendedor"],
  },
  {
    name: "Anticipadas",
    href: "/anticipadas",
    icon: TicketCheck,
    allowedRoles: ["admin", "vendedor"],
  },
  {
    name: "Promotores",
    href: "/promotores",
    icon: UserRound,
    allowedRoles: ["admin"],
  },
  {
    name: "Eventos",
    href: "/eventos",
    icon: Calendar,
    allowedRoles: ["admin"],
  },
  {
    name: "Listas",
    href: "/listas",
    icon: Users,
    allowedRoles: ["admin", "vendedor"],
  },
  {
    name: "Usuarios",
    href: "/usuarios",
    icon: UserPlus,
    allowedRoles: ["admin"],
  },
  {
    name: "Configuración entradas",
    href: "/configuracion",
    icon: Settings,
    allowedRoles: ["admin"],
  },
];

interface SidebarProps {
  isMobileOpen: boolean;
  onMobileClose: () => void;
}

export function Sidebar({ isMobileOpen, onMobileClose }: SidebarProps) {
  const { user, logout } = useAuth();
  const roleSlug: RoleSlug =
    user?.roleSlug ??
    normalizeRoleSlug(user?.rol_slug ?? user?.rol_nombre ?? null);
  const availableNavigation = navigation.filter(
    (item) => !item.allowedRoles || item.allowedRoles.includes(roleSlug),
  );

  const isMobile = useIsMobile();

  const handleNavigate = () => {
    if (isMobile) {
      onMobileClose();
    }
  };

  return (
    <aside
      className={cn(
        "fixed left-0 top-0 z-50 flex h-screen w-64 flex-col border-r border-border bg-sidebar shadow-lg transition-transform duration-300",
        isMobileOpen ? "translate-x-0" : "-translate-x-full",
        "lg:translate-x-0",
      )}
    >
      <div className="flex h-full flex-col gap-y-5 overflow-y-auto px-4 py-6">
        <div className="flex h-14 shrink-0 items-center justify-between">
          <div>
            <p className="text-lg font-bold text-sidebar-foreground">
              Santas Club
            </p>
            <p className="text-xs text-sidebar-foreground/70">
              Panel administrativo
            </p>
          </div>
          <button
            type="button"
            onClick={onMobileClose}
            className="rounded-md p-2 text-sidebar-foreground transition hover:bg-sidebar-accent lg:hidden"
          >
            <X className="h-4 w-4" />
            <span className="sr-only">Cerrar menú</span>
          </button>
        </div>
        <nav className="flex flex-1 flex-col">
          <ul role="list" className="flex flex-1 flex-col gap-y-2">
            {availableNavigation.map((item) => (
              <li key={item.name}>
                <NavLink
                  to={item.href}
                  end={item.href === "/"}
                  className={cn(
                    "group flex items-center gap-x-3 rounded-lg px-3 py-3 text-sm font-semibold leading-6",
                    "text-sidebar-foreground hover:bg-sidebar-accent hover:text-primary",
                    "transition-all duration-200",
                  )}
                  activeClassName="bg-sidebar-accent text-primary shadow-glow-primary"
                  onClick={handleNavigate}
                >
                  <item.icon className="h-6 w-6 shrink-0" aria-hidden="true" />
                  <span>{item.name}</span>
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>
        <div className="mt-auto space-y-3">
          <button
            type="button"
            onClick={logout}
            className="group flex w-full items-center justify-center gap-2 rounded-lg border border-border/50 px-3 py-2 text-sm font-semibold text-sidebar-foreground transition hover:border-primary/50 hover:text-primary"
          >
            <LogOut className="h-4 w-4" />
            <span>Cerrar sesión</span>
          </button>
        </div>
      </div>
    </aside>
  );
}
