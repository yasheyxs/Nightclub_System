import { FormEvent, useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { isAxiosError } from "axios";
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
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
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
  SaveUserPayload,
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
  password: string;
  passwordConfirm: string;
  currentPassword: string;
  newPassword: string;
  confirmNewPassword: string;
}
const MIN_PASSWORD_LENGTH = 8;

const createEmptyFormState = (): FormState => ({
  nombre: "",
  telefono: "",
  email: "",
  rolId: "",
  activo: true,
  password: "",
  passwordConfirm: "",
  currentPassword: "",
  newPassword: "",
  confirmNewPassword: "",
});

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
  if (isAxiosError(error)) {
    const data = error.response?.data;
    if (data && typeof data === "object" && "error" in data) {
      const message = (data as { error?: string }).error;
      if (message) {
        return message;
      }
    }
  }
  if (error instanceof Error) return error.message;
  return "Ocurrió un error inesperado. Por favor vuelve a intentarlo.";
};

export default function Usuarios() {
  const [formState, setFormState] = useState<FormState>(() =>
    createEmptyFormState(),
  );
  const [roles, setRoles] = useState<Role[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [reloading, setReloading] = useState<boolean>(false);
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [userToDelete, setUserToDelete] = useState<User | null>(null);
  const [deletePassword, setDeletePassword] = useState<string>("");
  const [deleteDialogOpen, setDeleteDialogOpen] = useState<boolean>(false);
  const [formDialogOpen, setFormDialogOpen] = useState<boolean>(false);

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
    setFormState(createEmptyFormState());
    setEditingUserId(null);
  };

  const resolveRoleName = (user?: User | null) => {
    if (!user) return "";
    return (user.rolNombre ?? roleById.get(user.rolId) ?? "").toLowerCase();
  };

  const requiresAdminPassword = (user?: User | null) => {
    const roleName = resolveRoleName(user);
    return roleName.includes("admin");
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

    const payload: SaveUserPayload = {
      nombre,
      telefono,
      email: email.length > 0 ? email : null,
      rolId: rolIdNumber,
      activo: formState.activo,
    };

    if (!isEditingUser) {
      const password = formState.password.trim();
      const passwordConfirm = formState.passwordConfirm.trim();

      if (!password) {
        toast({
          title: "Contraseña requerida",
          description: "Debes definir una contraseña para el nuevo usuario.",
        });
        return;
      }

      if (password.length < MIN_PASSWORD_LENGTH) {
        toast({
          title: "Contraseña muy corta",
          description: `La contraseña debe tener al menos ${MIN_PASSWORD_LENGTH} caracteres.`,
        });
        return;
      }

      if (password !== passwordConfirm) {
        toast({
          title: "Las contraseñas no coinciden",
          description: "Verifica los campos de contraseña.",
        });
        return;
      }

      payload.password = password;
    } else {
      const wantsPasswordChange = Boolean(
        formState.currentPassword ||
        formState.newPassword ||
        formState.confirmNewPassword,
      );

      if (wantsPasswordChange) {
        const currentPassword = formState.currentPassword.trim();
        const newPassword = formState.newPassword.trim();
        const confirmNewPassword = formState.confirmNewPassword.trim();

        if (!currentPassword) {
          toast({
            title: "Confirma tu contraseña actual",
            description:
              "Para cambiar la contraseña debes ingresar la contraseña actual o usar la opción 'Olvidé mi contraseña'.",
          });
          return;
        }

        if (!newPassword) {
          toast({
            title: "Nueva contraseña requerida",
            description: "Ingresa la nueva contraseña que quieres utilizar.",
          });
          return;
        }

        if (newPassword.length < MIN_PASSWORD_LENGTH) {
          toast({
            title: "Contraseña muy corta",
            description: `La nueva contraseña debe tener al menos ${MIN_PASSWORD_LENGTH} caracteres.`,
          });
          return;
        }

        if (newPassword === currentPassword) {
          toast({
            title: "Contraseña repetida",
            description: "La nueva contraseña debe ser distinta a la actual.",
          });
          return;
        }

        if (newPassword !== confirmNewPassword) {
          toast({
            title: "Las contraseñas no coinciden",
            description: "Verifica los campos de la nueva contraseña.",
          });
          return;
        }

        payload.currentPassword = currentPassword;
        payload.newPassword = newPassword;
      }
    }

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
      setFormDialogOpen(false);
    } catch (error) {
      toast({
        title: "Error al guardar usuario",
        description: getErrorMessage(error),
      });
    } finally {
      setSubmitting(false);
    }
  };

  const isEditingUser = editingUserId !== null;

  const openCreateDialog = () => {
    resetForm();
    setFormDialogOpen(true);
  };

  const handleEdit = (user: User) => {
    setEditingUserId(user.id);
    setFormState({
      nombre: user.nombre,
      telefono: user.telefono,
      email: user.email ?? "",
      rolId: user.rolId ? String(user.rolId) : "",
      activo: user.activo,
      password: "",
      passwordConfirm: "",
      currentPassword: "",
      newPassword: "",
      confirmNewPassword: "",
    });
    setFormDialogOpen(true);
  };

  const openDeleteDialog = (user: User) => {
    setUserToDelete(user);
    setDeletePassword("");
    setDeleteDialogOpen(true);
  };

  const closeDeleteDialog = () => {
    setDeleteDialogOpen(false);
    setUserToDelete(null);
    setDeletePassword("");
  };

  const handleConfirmDelete = async () => {
    if (!userToDelete) return;
    const needsPassword = requiresAdminPassword(userToDelete);

    if (needsPassword && deletePassword.trim().length === 0) {
      toast({
        title: "Contraseña requerida",
        description:
          "Debes ingresar la contraseña del usuario para eliminarlo.",
      });
      return;
    }

    setDeletingId(userToDelete.id);
    try {
      await deleteUser(userToDelete.id, {
        password: needsPassword ? deletePassword : undefined,
      });
      toast({ title: "Usuario eliminado" });
      await refreshUsers();
      closeDeleteDialog();
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
  const isEditing = isEditingUser;
  const deleteTargetIsAdmin = requiresAdminPassword(userToDelete);
  const isDeletingSelected = Boolean(
    userToDelete && deletingId !== null && deletingId === userToDelete.id,
  );

  return (
    <div className="space-y-6 p-2 sm:p-4 animate-fade-in">
      <Dialog
        open={formDialogOpen}
        onOpenChange={(open) => {
          setFormDialogOpen(open);
          if (!open) {
            resetForm();
          }
        }}
      >
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-center sm:text-left">
            <h1 className="text-2xl sm:text-4xl font-bold text-foreground mb-2">
              Gestión de Usuarios
            </h1>
            <p className="text-sm sm:text-base text-muted-foreground">
              Configura perfiles para administradores, cajas o promotores.
            </p>
          </div>
          <DialogTrigger asChild>
            <Button
              size="lg"
              className="w-full sm:w-auto"
              onClick={openCreateDialog}
            >
              Crear usuario
            </Button>
          </DialogTrigger>
        </div>

        <DialogContent className="max-h-[90vh] overflow-y-auto sm:min-w-[720px]">
          <DialogHeader>
            <DialogTitle>
              {isEditing ? "Editar usuario" : "Crear nuevo usuario"}
            </DialogTitle>
            <DialogDescription>
              {isEditing
                ? "Actualiza los datos seleccionados."
                : "Completa la información del nuevo usuario."}
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleSubmit} className="space-y-6 mt-4">
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

            {!isEditing && (
              <div className="grid gap-4 grid-cols-1 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label>Contraseña</Label>
                  <Input
                    type="password"
                    autoComplete="new-password"
                    value={formState.password}
                    onChange={(e) =>
                      setFormState((p) => ({
                        ...p,
                        password: e.target.value,
                      }))
                    }
                    placeholder="Ingresa una contraseña segura"
                  />
                </div>
                <div className="grid gap-2">
                  <Label>Confirmar contraseña</Label>
                  <Input
                    type="password"
                    autoComplete="new-password"
                    value={formState.passwordConfirm}
                    onChange={(e) =>
                      setFormState((p) => ({
                        ...p,
                        passwordConfirm: e.target.value,
                      }))
                    }
                    placeholder="Repite la contraseña"
                  />
                </div>
              </div>
            )}

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
            {isEditing && (
              <div className="space-y-4 rounded-lg border px-4 py-3">
                <div className="space-y-1">
                  <p className="text-sm font-medium">Cambiar contraseña</p>
                  <p className="text-xs text-muted-foreground">
                    Ingresa la contraseña actual y define una nueva. Si no la
                    recuerdas, puedes usar la opción
                    <Link
                      to="/recuperar"
                      className="ml-1 font-semibold text-primary underline-offset-2 hover:underline"
                    >
                      Olvidé mi contraseña
                    </Link>
                    .
                  </p>
                </div>
                <div className="grid gap-4 grid-cols-1 sm:grid-cols-3">
                  <div className="grid gap-2">
                    <Label>Contraseña actual</Label>
                    <Input
                      type="password"
                      autoComplete="current-password"
                      value={formState.currentPassword}
                      onChange={(e) =>
                        setFormState((p) => ({
                          ...p,
                          currentPassword: e.target.value,
                        }))
                      }
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>Nueva contraseña</Label>
                    <Input
                      type="password"
                      autoComplete="new-password"
                      value={formState.newPassword}
                      onChange={(e) =>
                        setFormState((p) => ({
                          ...p,
                          newPassword: e.target.value,
                        }))
                      }
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>Confirmar nueva contraseña</Label>
                    <Input
                      type="password"
                      autoComplete="new-password"
                      value={formState.confirmNewPassword}
                      onChange={(e) =>
                        setFormState((p) => ({
                          ...p,
                          confirmNewPassword: e.target.value,
                        }))
                      }
                    />
                  </div>
                </div>
              </div>
            )}

            <div className="flex flex-col sm:flex-row gap-3 sm:justify-end">
              {isEditing && (
                <Button
                  type="button"
                  variant="outline"
                  onClick={resetForm}
                  className="w-full sm:w-auto"
                >
                  Cancelar edición
                </Button>
              )}
              <Button
                type="submit"
                className="w-full sm:w-auto"
                disabled={submitting}
              >
                {submitting ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : isEditing ? (
                  "Guardar cambios"
                ) : (
                  "Crear usuario"
                )}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

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
                    onClick={() => openDeleteDialog(user)}
                    disabled={deletingId === user.id}
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
                            onClick={() => openDeleteDialog(user)}
                            disabled={deletingId === user.id}
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

      <AlertDialog
        open={deleteDialogOpen}
        onOpenChange={(open) => {
          setDeleteDialogOpen(open);
          if (!open) {
            setUserToDelete(null);
            setDeletePassword("");
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Eliminar usuario</AlertDialogTitle>
            <AlertDialogDescription>
              {userToDelete
                ? `¿Seguro que deseas eliminar a ${userToDelete.nombre}? Esta acción no se puede deshacer.`
                : "¿Seguro que deseas eliminar este usuario?"}
            </AlertDialogDescription>
          </AlertDialogHeader>

          {deleteTargetIsAdmin && (
            <div className="space-y-2">
              <Label>Contraseña del usuario administrador</Label>
              <Input
                type="password"
                autoComplete="current-password"
                value={deletePassword}
                onChange={(e) => setDeletePassword(e.target.value)}
                placeholder="Ingresa la contraseña para confirmar"
              />
              <p className="text-xs text-muted-foreground">
                Por seguridad, los administradores solo pueden eliminarse si
                confirman su contraseña. Si no la recuerdan, redirígelos a
                Olvidé mi contraseña.
              </p>
            </div>
          )}

          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDeletingSelected}>
              Cancelar
            </AlertDialogCancel>
            <Button
              type="button"
              variant="destructive"
              onClick={handleConfirmDelete}
              disabled={
                isDeletingSelected ||
                (deleteTargetIsAdmin && deletePassword.trim().length === 0)
              }
              className="gap-2"
            >
              {isDeletingSelected && (
                <Loader2 className="h-4 w-4 animate-spin" />
              )}
              Eliminar
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
