import { useEffect, useMemo, useState } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
  Plus,
  Edit,
  Trash2,
  Users,
  Crown,
  Loader2,
  Search,
  Printer,
} from "lucide-react";
import { toast } from "@/hooks/use-toast";
import { api } from "@/services/api";

interface Guest {
  id: string;
  fullName: string;
  document: string;
}

type UserRole = "promoter" | "owner";

interface ListUser {
  id: string;
  name: string;
  role: UserRole;
  code: string;
  guestList: Guest[];
}

interface GuestResponse {
  id: number;
  nombre_persona?: string;
  telefono?: string | null;
  ingreso?: boolean;
  fecha_registro?: string;
}

interface UserResponse {
  usuario_id: number;
  usuario_nombre: string;
  usuario_rol: string;
  usuario_codigo?: string | null;
  invitados: GuestResponse[] | null;
}

export default function Listas() {
  const [users, setUsers] = useState<ListUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedUserId, setSelectedUserId] = useState<string | null>(null);
  const [guestDialogOpen, setGuestDialogOpen] = useState(false);
  const [editingGuestId, setEditingGuestId] = useState<string | null>(null);
  const [printingGuestId, setPrintingGuestId] = useState<string | null>(null);
  const [guestForm, setGuestForm] = useState({ fullName: "", document: "" });

  // Filtros
  const [roleFilter, setRoleFilter] = useState<"all" | UserRole>("all");
  const [userSearch, setUserSearch] = useState("");
  const [guestSearch, setGuestSearch] = useState("");

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const { data } = await api.get<UserResponse[]>("/listas");
        if (Array.isArray(data)) {
          const formatted: ListUser[] = data.map((u) => ({
            id: String(u.usuario_id),
            name: u.usuario_nombre,
            role: u.usuario_rol === "owner" ? "owner" : "promoter",
            code: u.usuario_codigo ?? "",
            guestList: Array.isArray(u.invitados)
              ? u.invitados.map((g) => ({
                  id: String(g.id),
                  fullName: g.nombre_persona ?? "Sin nombre asignado",
                  document: g.telefono ?? "",
                }))
              : [],
          }));

          setUsers(formatted);
          setSelectedUserId(formatted[0]?.id ?? null);
        } else {
          throw new Error("Respuesta inválida del servidor");
        }
      } catch (error) {
        console.error("Error cargando listas:", error);
        toast({
          title: "Error",
          description: "No se pudieron cargar las listas de invitados.",
          variant: "destructive",
        });
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  // Filtrado combinado (rol + texto)
  const filteredUsers = useMemo(() => {
    return users.filter((u) => {
      const matchesRole = roleFilter === "all" ? true : u.role === roleFilter;
      const matchesSearch = u.name
        .toLowerCase()
        .includes(userSearch.toLowerCase());
      return matchesRole && matchesSearch;
    });
  }, [users, roleFilter, userSearch]);

  // Mantiene selección válida
  useEffect(() => {
    if (!selectedUserId || filteredUsers.some((u) => u.id === selectedUserId))
      return;
    setSelectedUserId(filteredUsers[0]?.id ?? null);
  }, [filteredUsers, selectedUserId]);

  const selectedUser = useMemo(
    () => users.find((u) => u.id === selectedUserId) ?? null,
    [users, selectedUserId]
  );

  const totalGuests = users.reduce((acc, u) => acc + u.guestList.length, 0);
  const promotersCount = users.filter((u) => u.role === "promoter").length;
  const ownersCount = users.filter((u) => u.role === "owner").length;

  const handleSaveGuest = async () => {
    if (!selectedUser) return;
    if (!guestForm.fullName.trim()) {
      toast({
        title: "Error",
        description: "El nombre del invitado es obligatorio.",
        variant: "destructive",
      });
      return;
    }

    try {
      if (editingGuestId) {
        const { data } = await api.put(`/listas?id=${editingGuestId}`, {
          nombre_persona: guestForm.fullName,
          telefono: guestForm.document,
        });
        setUsers((prev) =>
          prev.map((u) =>
            u.id === selectedUser.id
              ? {
                  ...u,
                  guestList: u.guestList.map((g) =>
                    g.id === editingGuestId
                      ? {
                          ...g,
                          fullName: data.nombre_persona,
                          document: data.telefono ?? "",
                        }
                      : g
                  ),
                }
              : u
          )
        );
        toast({ title: "Actualizado", description: "Invitado editado." });
      } else {
        const { data } = await api.post("/listas", {
          usuario_id: selectedUser.id,
          nombre_persona: guestForm.fullName,
          telefono: guestForm.document,
        });
        const newGuest: Guest = {
          id: String(data.id),
          fullName: data.nombre_persona,
          document: data.telefono ?? "",
        };
        setUsers((prev) =>
          prev.map((u) =>
            u.id === selectedUser.id
              ? { ...u, guestList: [...u.guestList, newGuest] }
              : u
          )
        );
        toast({ title: "Agregado", description: "Invitado agregado." });
      }
      setGuestDialogOpen(false);
      setEditingGuestId(null);
      setGuestForm({ fullName: "", document: "" });
    } catch {
      toast({
        title: "Error",
        description: "No se pudo guardar el invitado.",
        variant: "destructive",
      });
    }
  };

  const handleDeleteGuest = async (guestId: string) => {
    if (!window.confirm("¿Eliminar invitado?")) return;
    try {
      await api.delete(`/listas?id=${guestId}`);
      setUsers((prev) =>
        prev.map((u) =>
          u.id === selectedUserId
            ? {
                ...u,
                guestList: u.guestList.filter((g) => g.id !== guestId),
              }
            : u
        )
      );
      toast({ title: "Eliminado", description: "Invitado eliminado." });
    } catch {
      toast({
        title: "Error",
        description: "No se pudo eliminar el invitado.",
        variant: "destructive",
      });
    }
  };

  // Filtrado de invitados dentro del usuario seleccionado
  const filteredGuests = useMemo(() => {
    if (!selectedUser) return [];
    const q = guestSearch.toLowerCase();
    return selectedUser.guestList.filter(
      (g) =>
        g.fullName.toLowerCase().includes(q) ||
        g.document.toLowerCase().includes(q)
    );
  }, [selectedUser, guestSearch]);

  const handlePrintGuest = async (guest: Guest) => {
    setPrintingGuestId(guest.id);
    try {
      const payload = {
        nombre: guest.fullName || "Invitado de lista",
        lista: selectedUser?.name ?? "Lista",
        documento: guest.document ?? "",
      };

      const { data } = await api.post("/imprimir_ticket_gratis", payload);

      const mensaje =
        typeof data?.mensaje === "string"
          ? data.mensaje
          : "Ticket gratuito enviado a la impresora.";

      toast({
        title: "Impresión de ticket",
        description: mensaje,
      });
    } catch (error) {
      console.error("Error al imprimir ticket de lista:", error);
      toast({
        title: "No se pudo imprimir",
        description: "Reintentá en unos segundos.",
        variant: "destructive",
      });
    } finally {
      setPrintingGuestId(null);
    }
  };

  if (loading)
    return (
      <div className="flex justify-center py-20 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin mr-2" /> Cargando listas...
      </div>
    );

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Resumen */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Total de invitados</p>
            <p className="text-2xl font-bold">{totalGuests}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Promotores</p>
            <div className="flex items-center gap-2">
              <Users className="h-4 w-4 text-primary" />
              <p className="text-xl font-semibold">{promotersCount}</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Administradores</p>
            <div className="flex items-center gap-2">
              <Crown className="h-4 w-4 text-primary" />
              <p className="text-xl font-semibold">{ownersCount}</p>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filtros superiores */}
      <div className="flex flex-wrap gap-2 items-center">
        <Button
          variant={roleFilter === "all" ? "default" : "outline"}
          onClick={() => setRoleFilter("all")}
        >
          Todos
        </Button>
        <Button
          variant={roleFilter === "promoter" ? "default" : "outline"}
          onClick={() => setRoleFilter("promoter")}
        >
          Promotores
        </Button>
        <Button
          variant={roleFilter === "owner" ? "default" : "outline"}
          onClick={() => setRoleFilter("owner")}
        >
          Administradores
        </Button>

        <div className="ml-auto relative w-64">
          <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Buscar promotor o admin..."
            className="pl-8"
            value={userSearch}
            onChange={(e) => setUserSearch(e.target.value)}
          />
        </div>
      </div>

      {/* Listado */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Usuarios */}
        <Card className="lg:col-span-1">
          <CardContent className="p-4">
            <h2 className="font-semibold mb-2">Promotores / Admins</h2>
            <ScrollArea className="h-[400px]">
              {filteredUsers.map((u) => (
                <div
                  key={u.id}
                  onClick={() => setSelectedUserId(u.id)}
                  className={`p-2 rounded-md cursor-pointer ${
                    selectedUserId === u.id
                      ? "bg-primary text-primary-foreground"
                      : "hover:bg-muted"
                  }`}
                >
                  <div className="font-medium">{u.name}</div>
                  <div className="text-xs opacity-80">
                    {u.guestList.length} invitados
                  </div>
                </div>
              ))}
            </ScrollArea>
          </CardContent>
        </Card>

        {/* Invitados */}
        <Card className="lg:col-span-2">
          <CardContent className="p-4">
            <div className="flex justify-between items-center mb-3">
              <h2 className="font-semibold">
                Invitados de {selectedUser?.name || "—"}
              </h2>
              {selectedUser && (
                <Button
                  size="sm"
                  onClick={() => {
                    setEditingGuestId(null);
                    setGuestForm({ fullName: "", document: "" });
                    setGuestDialogOpen(true);
                  }}
                >
                  <Plus className="h-4 w-4 mr-1" /> Agregar
                </Button>
              )}
            </div>

            <div className="relative mb-3">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Buscar invitado..."
                className="pl-8"
                value={guestSearch}
                onChange={(e) => setGuestSearch(e.target.value)}
              />
            </div>

            {filteredGuests.length ? (
              <ScrollArea className="h-[400px]">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Nombre</TableHead>
                      <TableHead>Teléfono</TableHead>
                      <TableHead>Acciones</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredGuests.map((g) => (
                      <TableRow key={g.id}>
                        <TableCell>{g.fullName}</TableCell>
                        <TableCell>{g.document}</TableCell>
                        <TableCell className="flex gap-2">
                          <Button
                            size="sm"
                            variant="secondary"
                            disabled={printingGuestId === g.id}
                            onClick={() => handlePrintGuest(g)}
                          >
                            {printingGuestId === g.id ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              <Printer className="h-4 w-4" />
                            )}
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              setEditingGuestId(g.id);
                              setGuestForm({
                                fullName: g.fullName,
                                document: g.document,
                              });
                              setGuestDialogOpen(true);
                            }}
                          >
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => handleDeleteGuest(g.id)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </ScrollArea>
            ) : (
              <p className="text-sm text-muted-foreground">
                No hay invitados que coincidan con la búsqueda.
              </p>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Modal agregar/editar invitado */}
      <Dialog open={guestDialogOpen} onOpenChange={setGuestDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {editingGuestId ? "Editar invitado" : "Nuevo invitado"}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div>
              <Label>Nombre</Label>
              <Input
                value={guestForm.fullName}
                onChange={(e) =>
                  setGuestForm({ ...guestForm, fullName: e.target.value })
                }
              />
            </div>
            <div>
              <Label>Teléfono</Label>
              <Input
                value={guestForm.document}
                onChange={(e) =>
                  setGuestForm({ ...guestForm, document: e.target.value })
                }
              />
            </div>
          </div>
          <DialogFooter>
            <Button onClick={handleSaveGuest}>
              {editingGuestId ? "Guardar cambios" : "Agregar"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
