import {
  LayoutDashboard,
  Ticket,
  Calendar,
  Users,
  Settings,
  UserPlus,
  LogOut,
  type LucideIcon,
} from "lucide-react";
import { NavLink } from "@/components/NavLink";
import { cn } from "@/lib/utils";
import { useAuth } from "@/hooks/useAuth";
import { normalizeRoleSlug, type RoleSlug } from "@/lib/permissions";

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

export function Sidebar() {
  const { user, logout } = useAuth();
  const roleSlug: RoleSlug =
    user?.roleSlug ??
    normalizeRoleSlug(user?.rol_slug ?? user?.rol_nombre ?? null);
  const availableNavigation = navigation.filter(
    (item) => !item.allowedRoles || item.allowedRoles.includes(roleSlug)
  );

  return (
    <aside className="fixed left-0 top-0 z-40 h-screen w-20 lg:w-64 border-r border-border bg-sidebar transition-all duration-300">
      <div className="flex h-full flex-col gap-y-5 overflow-y-auto px-3 py-6">
        <div className="flex h-16 shrink-0 items-center justify-center lg:justify-start lg:px-3">
          <h1 className="hidden lg:block text-2xl font-bold bg-gradient-primary bg-clip-text text-transparent">
            Santas Club
          </h1>
          <span className="lg:hidden text-2xl font-bold text-primary">S</span>
        </div>
        <nav className="flex flex-1 flex-col">
          <ul role="list" className="flex flex-1 flex-col gap-y-2">
            {availableNavigation.map((item) => (
              <li key={item.name}>
                <NavLink
                  to={item.href}
                  end={item.href === "/"}
                  className={cn(
                    "group flex items-center gap-x-3 rounded-lg px-3 py-4 text-sm font-semibold leading-6",
                    "text-sidebar-foreground hover:bg-sidebar-accent hover:text-primary",
                    "transition-all duration-200"
                  )}
                  activeClassName="bg-sidebar-accent text-primary shadow-glow-primary"
                >
                  <item.icon className="h-6 w-6 shrink-0" aria-hidden="true" />
                  <span className="hidden lg:block">{item.name}</span>
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>
        <div className="mt-auto space-y-3">
          <div className="rounded-xl border border-border/60 bg-sidebar-accent/30 p-3 text-xs text-muted-foreground">
            <p className="text-sm font-semibold text-sidebar-foreground">
              {user?.nombre ?? "Usuario activo"}
            </p>
            <p className="text-sidebar-foreground/80">
              {user?.telefono ?? "Sin teléfono"}
            </p>
            <p className="truncate text-sidebar-foreground/60">
              {user?.email ?? "Sin correo"}
            </p>
          </div>
          <button
            type="button"
            onClick={logout}
            className="group flex w-full items-center justify-center gap-2 rounded-lg border border-border/50 px-3 py-2 text-sm font-semibold text-sidebar-foreground transition hover:border-primary/50 hover:text-primary"
          >
            <LogOut className="h-4 w-4" />
            <span className="hidden lg:inline">Cerrar sesión</span>
          </button>
        </div>
      </div>
    </aside>
  );
}
