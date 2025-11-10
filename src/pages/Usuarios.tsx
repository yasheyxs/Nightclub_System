import { FormEvent, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

const availableRoles = [
  { value: "admin", label: "Administrador" },
  { value: "caja-barra", label: "Caja o Barra" },
  { value: "promotor", label: "Promotor" },
];

export default function Usuarios() {
  const [formState, setFormState] = useState({
    name: "",
    role: "",
    isActive: true,
    notes: "",
  });

  const resetForm = () => {
    setFormState({ name: "", role: "", isActive: true, notes: "" });
  };

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">Gestión de Usuarios</h1>
          <p className="text-muted-foreground">
            Configura perfiles de acceso para administradores, cajas o barras y promotores.
          </p>
        </div>
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <Card className="border-border">
          <CardHeader>
            <CardTitle>Crear nuevo usuario</CardTitle>
            <CardDescription>
              Completa los datos básicos para habilitar un acceso personalizado al sistema.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label htmlFor="user-name">Nombre completo</Label>
                  <Input
                    id="user-name"
                    value={formState.name}
                    onChange={(event) => setFormState((prev) => ({ ...prev, name: event.target.value }))}
                    placeholder="Ingresar nombre"
                    className="h-12 text-base"
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="user-role">Rol</Label>
                  <Select
                    value={formState.role}
                    onValueChange={(value) => setFormState((prev) => ({ ...prev, role: value }))}
                  >
                    <SelectTrigger id="user-role" className="h-12 text-base">
                      <SelectValue placeholder="Seleccionar rol" />
                    </SelectTrigger>
                    <SelectContent>
                      {availableRoles.length > 0 ? (
                        availableRoles.map((role) => (
                          <SelectItem key={role.value} value={role.value}>
                            {role.label}
                          </SelectItem>
                        ))
                      ) : (
                        <div className="px-3 py-2 text-sm text-muted-foreground">
                          No hay roles configurados
                        </div>
                      )}
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="user-notes">Notas internas</Label>
                  <Textarea
                    id="user-notes"
                    value={formState.notes}
                    onChange={(event) => setFormState((prev) => ({ ...prev, notes: event.target.value }))}
                    placeholder="Agregar comentarios opcionales"
                    className="min-h-[120px] text-base"
                  />
                </div>
                <div className="flex items-center justify-between rounded-lg border border-border px-4 py-3">
                  <div>
                    <p className="text-sm font-medium text-foreground">Usuario activo</p>
                    <p className="text-sm text-muted-foreground">
                      Habilita o deshabilita el acceso cuando sea necesario.
                    </p>
                  </div>
                  <Switch
                    checked={formState.isActive}
                    onCheckedChange={(value) => setFormState((prev) => ({ ...prev, isActive: value }))}
                    aria-label="Estado del usuario"
                  />
                </div>
              </div>
              <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" size="lg" onClick={resetForm}>
                  Limpiar
                </Button>
                <Button type="submit" size="lg" className="bg-gradient-primary">
                  Guardar usuario
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card className="border-border">
          <CardHeader>
            <CardTitle>Usuarios registrados</CardTitle>
            <CardDescription>
              El listado se actualizará automáticamente cuando se conecte a la base de datos.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="rounded-lg border border-dashed border-border p-6 text-center">
              <p className="text-sm text-muted-foreground">
                Aún no hay usuarios cargados. Cuando se integren los datos podrás verlos aquí.
              </p>
            </div>
            <div className="rounded-lg border border-border overflow-hidden">
              <Table>
                <TableHeader className="bg-muted/40">
                  <TableRow>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Rol</TableHead>
                    <TableHead className="text-right">Estado</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow>
                    <TableCell colSpan={3} className="text-center text-sm text-muted-foreground py-10">
                      En espera de información de usuarios.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}