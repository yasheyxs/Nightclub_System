import { FormEvent, useState } from "react";
import { Link } from "react-router-dom";
import { Mail } from "lucide-react";
import { AuthLayout } from "@/components/AuthLayout";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { API_BASE_URL } from "@/lib/constants";
import { toast } from "sonner";

const ForgotPassword = () => {
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!email) {
      toast.error("Ingresá el correo asociado a la cuenta");
      return;
    }

    setLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/password_reset`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email }),
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data?.error ?? "No pudimos enviar las instrucciones");
      }
      toast.success("Enviamos una contraseña temporal a tu correo");
    } catch (error) {
      const message =
        error instanceof Error
          ? error.message
          : "Error al recuperar la contraseña";
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout
      title="Recuperar acceso"
      subtitle="Te enviaremos una nueva contraseña a tu correo"
      footer={
        <p className="text-center text-sm text-muted-foreground">
          ¿Recordaste tu contraseña?{" "}
          <Link to="/login" className="text-primary hover:underline">
            Iniciar sesión
          </Link>
        </p>
      }
    >
      <form className="space-y-5" onSubmit={handleSubmit}>
        <div className="space-y-2">
          <Label htmlFor="email" className="text-foreground">
            Correo asociado
          </Label>
          <div className="relative">
            <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
            <Input
              id="email"
              type="email"
              autoComplete="email"
              className="pl-10 bg-surface-elevated border-border"
              placeholder="correo@santasclub.com"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
            />
          </div>
        </div>

        <Button
          type="submit"
          disabled={loading}
          className="w-full bg-gradient-primary shadow-neon hover:shadow-neon-intense"
        >
          {loading ? "Enviando..." : "Enviar instrucciones"}
        </Button>
      </form>
    </AuthLayout>
  );
};

export default ForgotPassword;
