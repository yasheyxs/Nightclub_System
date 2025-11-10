import { FormEvent, useEffect, useMemo, useState } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Loader2, Pencil, Trash2 } from "lucide-react";
import { toast } from "@/components/ui/use-toast";
import {
  Role,
  User,
  createUser,
  deleteUser,
  listRoles,
  listUsers,
  updateUser,
} from "@/services/users";

interface FormState {
  nombre: string;
  telefono: string;
  email: string;
  rolId: string;
  activo: boolean;
}

const EMPTY_FORM: FormState = {
  nombre: "",
  telefono: "",
  email: "",
  rolId: "",
  activo: true,
};

const formatDate = (value?: string | null) => {
  if (!value) return "—";
  const parsedDate = new Date(value);
  if (Number.isNaN(parsedDate.getTime())) {
    return value;
  }
  return new Intl.DateTimeFormat("es-AR", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(parsedDate);
};

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  return "Ocurrió un error inesperado. Por favor vuelve a intentarlo.";
};

export default function Usuarios() {
  const [formState, setFormState] = useState<FormState>(EMPTY_FORM);
  const [roles, setRoles] = useState<Role[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [reloading, setReloading] = useState<boolean>(false);
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [editingUserId, setEditingUserId] = useState<number | null>(null);

  const roleById = useMemo(() => {
    const map = new Map<number, string>();
    roles.forEach((role) => {
      map.set(role.id, role.nombre);
    });
    return map;
  }, [roles]);

  const fetchInitialData = async () => {
    setLoading(true);
    try {
      const [rolesData, usersData] = await Promise.all([
        listRoles(),
        listUsers(),
      ]);
      setRoles(rolesData);
      setUsers(usersData);
    } catch (error) {
      toast({
        title: "Error al cargar datos",
        description: getErrorMessage(error),
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchInitialData();
  }, []);

  const refreshUsers = async () => {
    setReloading(true);
    try {
      const usersData = await listUsers();
      setUsers(usersData);
    } catch (error) {
      toast({
        title: "No fue posible actualizar el listado",
        description: getErrorMessage(error),
      });
    } finally {
      setReloading(false);
    }
  };

  const resetForm = () => {
    setFormState(EMPTY_FORM);
    setEditingUserId(null);
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const nombre = formState.nombre.trim();
    const telefono = formState.telefono.trim();
    const email = formState.email.trim();
    const rolIdNumber = Number(formState.rolId);

    if (!nombre || !telefono || !rolIdNumber) {
      toast({
        title: "Datos incompletos",
        description: "Nombre, teléfono y rol son obligatorios.",
      });
      return;
    }

    const payload = {
      nombre,
      telefono,
      email: email.length > 0 ? email : null,
      rolId: rolIdNumber,
      activo: formState.activo,
    };

    setSubmitting(true);
    try {
      if (editingUserId) {
        await updateUser(editingUserId, payload);
        toast({ title: "Usuario actualizado" });
      } else {
        await createUser(payload);
        toast({ title: "Usuario creado" });
      }
      await refreshUsers();
      resetForm();
    } catch (error) {
      toast({
        title: "Error al guardar usuario",
        description: getErrorMessage(error),
      });
    } finally {
      setSubmitting(false);
    }
  };

  const handleEdit = (user: User) => {
    setEditingUserId(user.id);
    setFormState({
      nombre: user.nombre,
      telefono: user.telefono,
      email: user.email ?? "",
      rolId: user.rolId ? String(user.rolId) : "",
      activo: user.activo,
    });
  };

  const handleDelete = async (user: User) => {
    const confirmed = window.confirm(`¿Eliminar a ${user.nombre}?`);
    if (!confirmed) return;
    setDeletingId(user.id);
    try {
      await deleteUser(user.id);
      await refreshUsers();
    } catch (error) {
      toast({
        title: "Error al eliminar",
        description: getErrorMessage(error),
      });
    } finally {
      setDeletingId(null);
    }
  };

  const isLoadingUsers = loading || reloading;
  const isEditing = editingUserId !== null;

  return (
    <div className="space-y-6 p-2 sm:p-4 animate-fade-in">
      <div className="text-center sm:text-left">
        <h1 className="text-2xl sm:text-4xl font-bold text-foreground mb-2">
          Gestión de Usuarios
        </h1>
        <p className="text-sm sm:text-base text-muted-foreground">
          Configura perfiles para administradores, cajas o promotores.
        </p>
      </div>

      <Card className="border-border">
        <CardHeader>
          <CardTitle>
            {isEditing ? "Editar usuario" : "Crear nuevo usuario"}
          </CardTitle>
          <CardDescription>
            {isEditing
              ? "Actualiza los datos seleccionados."
              : "Completa la información del nuevo usuario."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid gap-4 grid-cols-1 sm:grid-cols-2">
              <div className="grid gap-2">
                <Label>Nombre completo</Label>
                <Input
                  value={formState.nombre}
                  onChange={(e) =>
                    setFormState((p) => ({ ...p, nombre: e.target.value }))
                  }
                  placeholder="Ingresar nombre"
                />
              </div>
              <div className="grid gap-2">
                <Label>Teléfono</Label>
                <Input
                  value={formState.telefono}
                  onChange={(e) =>
                    setFormState((p) => ({ ...p, telefono: e.target.value }))
                  }
                  placeholder="Ej: +54 9 11 5555-5555"
                />
              </div>
              <div className="grid gap-2">
                <Label>Correo electrónico</Label>
                <Input
                  type="email"
                  value={formState.email}
                  onChange={(e) =>
                    setFormState((p) => ({ ...p, email: e.target.value }))
                  }
                  placeholder="usuario@santasclub.com"
                />
              </div>
              <div className="grid gap-2">
                <Label>Rol</Label>
                <Select
                  value={formState.rolId}
                  onValueChange={(value) =>
                    setFormState((p) => ({ ...p, rolId: value }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Seleccionar rol" />
                  </SelectTrigger>
                  <SelectContent>
                    {roles.length === 0 ? (
                      <div className="px-3 py-2 text-sm text-muted-foreground">
                        No hay roles disponibles
                      </div>
                    ) : (
                      roles.map((role) => (
                        <SelectItem key={role.id} value={String(role.id)}>
                          {role.nombre}
                        </SelectItem>
                      ))
                    )}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg border px-4 py-3">
              <div>
                <p className="text-sm font-medium">Usuario activo</p>
                <p className="text-xs text-muted-foreground">
                  Habilita o deshabilita el acceso.
                </p>
              </div>
              <Switch
                checked={formState.activo}
                onCheckedChange={(checked) =>
                  setFormState((p) => ({ ...p, activo: checked }))
                }
              />
            </div>

            <div className="flex flex-col sm:flex-row gap-3 sm:justify-end">
              <Button
                type="button"
                variant="outline"
                onClick={resetForm}
                disabled={submitting}
              >
                {isEditing ? "Cancelar" : "Limpiar"}
              </Button>
              <Button
                type="submit"
                className="bg-gradient-primary gap-2"
                disabled={submitting}
              >
                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                {isEditing ? "Guardar cambios" : "Guardar usuario"}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      {/* === TABLA RESPONSIVE === */}
      <Card className="border-border">
        <CardHeader>
          <CardTitle>Usuarios registrados</CardTitle>
        </CardHeader>
        <CardContent>
          {/* Vista móvil */}
          <div className="block md:hidden space-y-4">
            {users.map((user) => (
              <div
                key={user.id}
                className="border rounded-lg p-4 flex flex-col gap-2"
              >
                <div className="flex justify-between">
                  <span className="font-semibold">{user.nombre}</span>
                  <Badge
                    className={
                      user.activo
                        ? "bg-green-100 text-green-700"
                        : "bg-gray-200 text-gray-600"
                    }
                  >
                    {user.activo ? "Activo" : "Inactivo"}
                  </Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                  📞 {user.telefono}
                </p>
                <p className="text-sm">
                  Rol: {roleById.get(user.rolId) ?? "—"}
                </p>
                <p className="text-xs text-muted-foreground">
                  Creado: {formatDate(user.fechaCreacion)}
                </p>
                <div className="flex justify-end gap-2 mt-2">
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleEdit(user)}
                  >
                    <Pencil className="h-4 w-4" />
                  </Button>
                  <Button
                    size="sm"
                    variant="destructive"
                    onClick={() => handleDelete(user)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            ))}
          </div>

          {/* Vista escritorio */}
          <div className="hidden md:block overflow-x-auto">
            <Table>
              <TableHeader className="bg-muted/40">
                <TableRow>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Teléfono</TableHead>
                  <TableHead>Rol</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead>Creado</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoadingUsers ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-10">
                      <Loader2 className="h-4 w-4 animate-spin inline mr-2" />
                      Cargando usuarios...
                    </TableCell>
                  </TableRow>
                ) : users.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={6}
                      className="text-center py-10 text-muted-foreground"
                    >
                      No hay usuarios registrados.
                    </TableCell>
                  </TableRow>
                ) : (
                  users.map((user) => (
                    <TableRow key={user.id}>
                      <TableCell>{user.nombre}</TableCell>
                      <TableCell>{user.telefono}</TableCell>
                      <TableCell>
                        {roleById.get(user.rolId) ?? user.rolNombre ?? "—"}
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant="outline"
                          className={
                            user.activo
                              ? "border-green-400 bg-green-100 text-green-700"
                              : "border-gray-300 bg-gray-100 text-gray-500"
                          }
                        >
                          {user.activo ? "Activo" : "Inactivo"}
                        </Badge>
                      </TableCell>
                      <TableCell>{formatDate(user.fechaCreacion)}</TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-2">
                          <Button
                            variant="outline"
                            size="icon"
                            onClick={() => handleEdit(user)}
                            className="h-8 w-8"
                          >
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="destructive"
                            size="icon"
                            onClick={() => handleDelete(user)}
                            className="h-8 w-8"
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
