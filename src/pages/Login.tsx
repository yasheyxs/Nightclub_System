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

    // Validar formato de teléfono (Ejemplo: al menos 10 dígitos)
    const phonePattern = /^[0-9]{10,}$/;
    if (!phonePattern.test(telefono)) {
      toast.error("Por favor ingresa un teléfono válido");
      return;
    }

    setLoading(true);
    try {
      const authenticatedUser = await login(telefono, password);
      const roleSlug: RoleSlug =
        authenticatedUser.roleSlug ??
        normalizeRoleSlug(
          authenticatedUser.rol_slug ?? authenticatedUser.rol_nombre ?? null
        );
      toast.success("Sesión iniciada correctamente");
      const destination = ensureAllowedRoute(from, roleSlug);
      navigate(destination, { replace: true });
    } catch (error) {
      // Asegúrate de capturar un error si es una cadena o cualquier tipo
      const message =
        error instanceof Error ? error.message : "No se pudo iniciar sesión";
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!user) return;
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
      footer={null} // 👈 SIN REGISTRO
    >
      <form className="space-y-5" onSubmit={handleSubmit}>
        <div className="space-y-2">
          <Label htmlFor="telefono" className="text-foreground">
            Teléfono
          </Label>
          <div className="relative">
            <Phone className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
            <Input
              id="telefono"
              type="tel"
              autoComplete="tel"
              className="pl-10 bg-surface-elevated border-border"
              placeholder="351 555 4444"
              value={telefono}
              onChange={(event) => setTelefono(event.target.value)}
              pattern="^[0-9]{10,}$" // Validación de 10 dígitos mínimo
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="password" className="text-foreground">
            Contraseña
          </Label>
          <div className="relative">
            <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              className="pl-10 bg-surface-elevated border-border"
              placeholder="••••••••"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
          </div>
        </div>

        <Button
          type="submit"
          disabled={loading}
          className="w-full bg-gradient-primary shadow-neon hover:shadow-neon-intense transition-all"
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
