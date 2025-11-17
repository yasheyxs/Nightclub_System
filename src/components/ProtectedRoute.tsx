import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";
import {
  ensureAllowedRoute,
  isRouteAllowed,
  normalizeRoleSlug,
  type RoleSlug,
} from "@/lib/permissions";

export function ProtectedRoute() {
  const { user } = useAuth();
  const location = useLocation();

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  const roleSlug: RoleSlug =
    user.roleSlug ??
    normalizeRoleSlug(user.rol_slug ?? user.rol_nombre ?? null);

  if (!isRouteAllowed(location.pathname, roleSlug)) {
    const destination = ensureAllowedRoute(location.pathname, roleSlug);
    if (destination === "/login") {
      return <Navigate to="/login" replace state={{ from: location }} />;
    }
    return <Navigate to={destination} replace />;
  }

  return <Outlet />;
}
