import { LayoutDashboard, Ticket, Package, Calendar, Users, BarChart3, Settings, ShoppingCart } from "lucide-react";
import { NavLink } from "@/components/NavLink";
import { cn } from "@/lib/utils";

const navigation = [
  { name: "Dashboard", href: "/", icon: LayoutDashboard },
  { name: "Entradas", href: "/entradas", icon: Ticket },
  { name: "Stock", href: "/stock", icon: Package },
  { name: "Eventos", href: "/eventos", icon: Calendar },
  { name: "Promotores", href: "/promotores", icon: Users },
  { name: "Ventas", href: "/ventas", icon: ShoppingCart },
  { name: "Reportes", href: "/reportes", icon: BarChart3 },
  { name: "Configuración", href: "/configuracion", icon: Settings },
];

export function Sidebar() {
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
            {navigation.map((item) => (
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
      </div>
    </aside>
  );
}
