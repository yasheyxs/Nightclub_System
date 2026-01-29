export type RoleSlug = "admin" | "vendedor" | "promotor" | "unknown";

const APP_ROUTES = [
  "/",
  "/entradas",
  "/anticipadas",
  "/promotores",
  "/eventos",
  "/listas",
  "/usuarios",
  "/configuracion",
] as const;

type AppRoute = (typeof APP_ROUTES)[number];

const ROUTES_BY_ROLE: Record<RoleSlug, AppRoute[]> = {
  admin: [...APP_ROUTES],
  vendedor: ["/entradas", "/anticipadas", "/listas"],
  promotor: [],
  unknown: ["/entradas"],
};

const ROLE_ALIASES: Record<string, RoleSlug> = {
  admin: "admin",
  administrador: "admin",
  administradora: "admin",
  seller: "vendedor",
  vendedor: "vendedor",
  vendedora: "vendedor",
  promotor: "promotor",
  promotora: "promotor",
  promoter: "promotor",
};

const normalizePath = (path?: string | null): string => {
  if (!path) return "/";
  return path.startsWith("/") ? path : `/${path}`;
};

export const normalizeRoleSlug = (
  roleName?: string | null,
): RoleSlug => {
  if (!roleName) return "unknown";
  const slug = roleName.trim().toLowerCase();
  return ROLE_ALIASES[slug] ?? "unknown";
};

export const getAllowedRoutes = (role: RoleSlug): string[] => {
  return ROUTES_BY_ROLE[role] ?? ROUTES_BY_ROLE.unknown;
};

export const getDefaultRoute = (role: RoleSlug): string => {
  const allowed = getAllowedRoutes(role);
  return allowed[0] ?? "/login";
};

export const isRouteAllowed = (pathname: string, role: RoleSlug): boolean => {
  const normalizedPath = normalizePath(pathname);
  const allowedRoutes = getAllowedRoutes(role);
  if (!allowedRoutes.length) return false;
  return allowedRoutes.some((route) => {
    const normalizedRoute = normalizePath(route);
    if (normalizedRoute === "/") {
      return normalizedPath === "/";
    }
    return (
      normalizedPath === normalizedRoute ||
      normalizedPath.startsWith(`${normalizedRoute}/`)
    );
  });
};

export const ensureAllowedRoute = (pathname: string, role: RoleSlug): string => {
  if (isRouteAllowed(pathname, role)) {
    return normalizePath(pathname);
  }
  return getDefaultRoute(role);
};