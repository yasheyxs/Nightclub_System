import { FormEvent, useEffect, useState } from "react";
import {
  Link,
  useLocation,
  useNavigate,
  type Location,
} from "react-router-dom";
import { Phone, Lock } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { AuthLayout } from "@/components/AuthLayout";
import { useAuth } from "@/hooks/useAuth";
import { toast } from "sonner";
import {
  ensureAllowedRoute,
  normalizeRoleSlug,
  type RoleSlug,
} from "@/lib/permissions";

type AuthenticatedUserLike = {
  id?: number | string | null;
  user_id?: number | string | null;
  usuario_id?: number | string | null;
  idUsuario?: number | string | null;
  nombre?: string | null;
  email?: string | null;
  rol_id?: number | string | null;
  rol_nombre?: string | null;
  rol_slug?: string | null;
  roleSlug?: RoleSlug | null;
  token?: string | null;
  user?: {
    id?: number | string | null;
    user_id?: number | string | null;
    usuario_id?: number | string | null;
    idUsuario?: number | string | null;
    nombre?: string | null;
    email?: string | null;
    rol_id?: number | string | null;
    rol_nombre?: string | null;
    rol_slug?: string | null;
    roleSlug?: RoleSlug | null;
  } | null;
};

const extractNumericId = (value: unknown): number | null => {
  if (typeof value === "number" && Number.isFinite(value) && value > 0) {
    return value;
  }

  if (typeof value === "string") {
    const parsed = Number(value);
    if (Number.isFinite(parsed) && parsed > 0) {
      return parsed;
    }
  }

  return null;
};

const persistAuthenticatedUser = (authenticatedUser: AuthenticatedUserLike) => {
  const normalizedUser = authenticatedUser?.user ?? authenticatedUser ?? null;

  if (!normalizedUser) {
    return;
  }

  const userId =
    extractNumericId(normalizedUser.id) ??
    extractNumericId(normalizedUser.user_id) ??
    extractNumericId(normalizedUser.usuario_id) ??
    extractNumericId(normalizedUser.idUsuario);

  localStorage.setItem("user", JSON.stringify(normalizedUser));
  localStorage.setItem("authUser", JSON.stringify(normalizedUser));

  if (userId !== null) {
    const userIdString = String(userId);
    localStorage.setItem("user_id", userIdString);
    localStorage.setItem("usuario_id", userIdString);
    localStorage.setItem("id", userIdString);
    localStorage.setItem("idUsuario", userIdString);
  }

  if ("token" in authenticatedUser && authenticatedUser.token) {
    localStorage.setItem("token", authenticatedUser.token);
  }
};

const Login = () => {
  const { login, user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const [telefono, setTelefono] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);

  const from = (location.state as { from?: Location })?.from?.pathname || "/";

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!telefono || !password) {
      toast.error("Completá tu teléfono y contraseña");
      return;
    }

    const phonePattern = /^[0-9]{10,}$/;
    if (!phonePattern.test(telefono)) {
      toast.error("Por favor ingresa un teléfono válido");
      return;
    }

    setLoading(true);

    try {
      const authenticatedUser = (await login(
        telefono,
        password,
      )) as AuthenticatedUserLike;

      persistAuthenticatedUser(authenticatedUser);

      const normalizedUser = authenticatedUser?.user ?? authenticatedUser;

      const roleSlug: RoleSlug =
        normalizedUser.roleSlug ??
        normalizeRoleSlug(
          normalizedUser.rol_slug ?? normalizedUser.rol_nombre ?? null,
        );

      toast.success("Sesión iniciada correctamente");

      const destination = ensureAllowedRoute(from, roleSlug);
      navigate(destination, { replace: true });
    } catch (error) {
      const message =
        error instanceof Error ? error.message : "No se pudo iniciar sesión";
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!user) return;

    persistAuthenticatedUser(user as AuthenticatedUserLike);

    const roleSlug: RoleSlug =
      user.roleSlug ??
      normalizeRoleSlug(user.rol_slug ?? user.rol_nombre ?? null);

    const destination = ensureAllowedRoute(from, roleSlug);
    navigate(destination, { replace: true });
  }, [from, navigate, user]);

  return (
    <AuthLayout
      title="Iniciar sesión"
      subtitle="Administrá Santas Club con seguridad"
      footer={null}
    >
      <form className="space-y-5" onSubmit={handleSubmit}>
        <div className="space-y-2">
          <Label htmlFor="telefono" className="text-foreground">
            Teléfono
          </Label>

          <div className="relative">
            <Phone className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" />

            <Input
              id="telefono"
              type="tel"
              autoComplete="tel"
              className="border-border bg-surface-elevated pl-10 text-black placeholder:text-muted-foreground"
              placeholder="3515554444"
              value={telefono}
              onChange={(event) =>
                setTelefono(event.target.value.replace(/\D/g, ""))
              }
              pattern="^[0-9]{10,}$"
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="password" className="text-foreground">
            Contraseña
          </Label>

          <div className="relative">
            <Lock className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" />

            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              className="border-border bg-surface-elevated pl-10 text-black placeholder:text-muted-foreground"
              placeholder="••••••••"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
          </div>
        </div>

        <Button
          type="submit"
          disabled={loading}
          className="w-full bg-gradient-primary shadow-neon transition-all hover:shadow-neon-intense"
        >
          {loading ? "Ingresando..." : "Ingresar"}
        </Button>

        <p className="text-center text-sm text-muted-foreground">
          ¿Olvidaste tu contraseña?{" "}
          <Link to="/recuperar" className="text-primary hover:underline">
            Recuperar acceso
          </Link>
        </p>
      </form>
    </AuthLayout>
  );
};

export default Login;
