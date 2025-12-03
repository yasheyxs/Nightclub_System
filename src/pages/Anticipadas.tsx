import { useEffect, useMemo, useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { ScrollArea } from "@/components/ui/scroll-area";
import { toast } from "@/hooks/use-toast";
import { api } from "@/services/api";
import { Loader2, Printer, Search, Trash2, UserRoundPlus } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

interface AnticipadaResponse {
  id: number;
  nombre: string;
  dni?: string | null;
  entrada_id: number;
  evento_id?: number | null;
  cantidad?: number | null;
  incluye_trago?: boolean;
  entrada_nombre?: string | null;
  entrada_precio?: number | null;
  evento_nombre?: string | null;
}

interface AnticipadaItem {
  id: number;
  nombre: string;
  dni: string;
  entradaNombre: string;
  cantidad: number;
  incluyeTrago: boolean;
  eventoNombre: string;
}

interface EntradaOption {
  id: number;
  nombre: string;
  precio_base?: number | null;
}

interface EventoOption {
  id: number;
  nombre: string;
  fecha: string | null;
}

const normalizeEntradaName = (name: string) =>
  name
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();

const mapAnticipada = (item: AnticipadaResponse): AnticipadaItem => ({
  id: Number(item.id),
  nombre: item.nombre ?? "Sin nombre",
  dni: item.dni ?? "-",
  entradaNombre: item.entrada_nombre ?? "Anticipada",
  cantidad: Number(item.cantidad ?? 1),
  incluyeTrago: Boolean(item.incluye_trago),
  eventoNombre: item.evento_nombre ?? "—",
});

export default function Anticipadas() {
  const [anticipadas, setAnticipadas] = useState<AnticipadaItem[]>([]);
  const [entradasAnticipadas, setEntradasAnticipadas] = useState<
    EntradaOption[]
  >([]);
  const [eventos, setEventos] = useState<EventoOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [optionsLoading, setOptionsLoading] = useState(true);
  const [formOpen, setFormOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [printingId, setPrintingId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<AnticipadaItem | null>(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [formData, setFormData] = useState({
    nombre: "",
    dni: "",
    entradaId: "",
    eventoId: "",
    cantidad: 1,
    incluyeTrago: false,
  });

  const resetForm = () => {
    setFormData((prev) => ({
      ...prev,
      nombre: "",
      dni: "",
      cantidad: 1,
      incluyeTrago: false,
    }));
  };

  const fetchAnticipadas = async () => {
    setLoading(true);
    try {
      const { data } = await api.get<AnticipadaResponse[]>("/anticipadas");
      const mapped: AnticipadaItem[] = (data ?? []).map(mapAnticipada);
      setAnticipadas(mapped);
    } catch (error) {
      console.error("Error cargando anticipadas:", error);
      toast({
        title: "Error",
        description: "No se pudieron cargar las anticipadas.",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  const fetchOptions = async () => {
    setOptionsLoading(true);
    try {
      const [entradasRes, eventosRes] = await Promise.all([
        api.get<EntradaOption[]>("/entradas"),
        api.get<EventoOption[]>("/eventos?upcoming=1"),
      ]);

      const anticipadasOptions = (entradasRes.data ?? []).filter(
        (entrada) => normalizeEntradaName(entrada.nombre) === "anticipada"
      );
      setEntradasAnticipadas(anticipadasOptions);

      if (!formData.entradaId && anticipadasOptions.length > 0) {
        setFormData((prev) => ({
          ...prev,
          entradaId: String(anticipadasOptions[0].id),
        }));
      }
      const mappedEventos = (eventosRes.data ?? []).map((evento) => ({
        id: Number(evento.id),
        nombre: evento.nombre,
        fecha: evento.fecha,
      }));
      setEventos(mappedEventos);
      if (mappedEventos.length > 0) {
        const proximoEvento = mappedEventos[0]; // el primero siempre es el próximo upcoming
        setFormData((prev) => ({
          ...prev,
          eventoId: String(proximoEvento.id),
        }));
      } else {
        // si NO hay eventos, dejamos none
        setFormData((prev) => ({
          ...prev,
          eventoId: "",
        }));
      }
    } catch (error) {
      console.error("Error cargando opciones de anticipadas:", error);
      toast({
        title: "Error",
        description: "No se pudieron cargar los eventos o entradas.",
        variant: "destructive",
      });
    } finally {
      setOptionsLoading(false);
    }
  };

  useEffect(() => {
    fetchAnticipadas();
    fetchOptions();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const filteredAnticipadas = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return anticipadas;
    return anticipadas.filter((item) =>
      `${item.nombre} ${item.dni} ${item.eventoNombre} ${item.entradaNombre}`
        .toLowerCase()
        .includes(query)
    );
  }, [anticipadas, search]);

  const handlePrint = async (itemId: number) => {
    setPrintingId(itemId);
    try {
      const { data } = await api.post("/anticipadas", {
        accion: "imprimir",
        id: itemId,
      });

      const mensaje =
        typeof data?.mensaje === "string"
          ? data.mensaje
          : "Ticket enviado a impresión.";

      setAnticipadas((prev) => prev.filter((item) => item.id !== itemId));
      toast({
        title: "Ticket impreso",
        description: mensaje,
      });
    } catch (error) {
      console.error("Error al imprimir anticipada:", error);
      toast({
        title: "No se pudo imprimir",
        description: "Reintentá en unos segundos.",
        variant: "destructive",
      });
    } finally {
      setPrintingId(null);
    }
  };

  const handleCreate = async () => {
    if (!formData.nombre.trim() || !formData.entradaId) {
      toast({
        title: "Datos incompletos",
        description: "Ingresá el nombre.",
        variant: "destructive",
      });
      return;
    }

    setCreating(true);
    try {
      const { data } = await api.post("/anticipadas", {
        accion: "crear",
        nombre: formData.nombre.trim(),
        dni: formData.dni.trim(),
        evento_id: formData.eventoId ? Number(formData.eventoId) : null,
        cantidad: formData.cantidad,
        incluye_trago: formData.incluyeTrago,
      });

      const nueva = data?.anticipada as AnticipadaResponse | undefined;

      if (nueva) {
        setAnticipadas((prev) => [mapAnticipada(nueva), ...prev]);
        toast({
          title: "Anticipada registrada",
          description: data?.mensaje ?? "Se agregó al listado.",
        });
        resetForm();
        setFormOpen(false);
      }
    } catch (error) {
      console.error("Error al registrar anticipada:", error);
      toast({
        title: "No se pudo registrar",
        description: "Revisá los datos e intentá nuevamente.",
        variant: "destructive",
      });
    } finally {
      setCreating(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeletingId(deleteTarget.id);
    try {
      await api.post("/anticipadas", {
        accion: "eliminar",
        id: deleteTarget.id,
      });

      setAnticipadas((prev) =>
        prev.filter((anticipada) => anticipada.id !== deleteTarget.id)
      );
      toast({
        title: "Registro eliminado",
        description: "Se quitó de la lista de anticipadas.",
      });
      setDeleteDialogOpen(false);
      setDeleteTarget(null);
    } catch (error) {
      console.error("Error al eliminar anticipada:", error);
      toast({
        title: "No se pudo eliminar",
        description: "Reintentá en unos segundos.",
        variant: "destructive",
      });
    } finally {
      setDeletingId(null);
    }
  };

  const selectedEntrada = entradasAnticipadas[0] ?? null;

  const entradaPrice = selectedEntrada?.precio_base ?? null;
  const totalPrice =
    entradaPrice !== null
      ? Math.max(1, formData.cantidad) * entradaPrice
      : null;

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">
            Anticipadas
          </h1>
          <p className="text-muted-foreground">
            Gestioná las compras anticipadas y enviá los tickets a impresión.
          </p>
        </div>
        <Dialog
          open={formOpen}
          onOpenChange={(isOpen) => {
            setFormOpen(isOpen);
            if (!isOpen) {
              resetForm();
            }
          }}
        >
          <DialogTrigger asChild>
            <Button size="lg" className="gap-2">
              <UserRoundPlus className="h-5 w-5" />
              Registrar
            </Button>
          </DialogTrigger>
          <DialogContent className="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-2xl">
                Registrar anticipada
              </DialogTitle>
            </DialogHeader>
            <div className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="nombre">Nombre y apellido</Label>
                  <Input
                    id="nombre"
                    value={formData.nombre}
                    onChange={(e) =>
                      setFormData({ ...formData, nombre: e.target.value })
                    }
                    placeholder="Ej: María Pérez"
                    className="h-11"
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="dni">DNI</Label>
                  <Input
                    id="dni"
                    value={formData.dni}
                    onChange={(e) =>
                      setFormData({ ...formData, dni: e.target.value })
                    }
                    placeholder="Opcional"
                    className="h-11"
                  />
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-3">
                <div className="space-y-2 md:col-span-2">
                  <Label>Entrada anticipada</Label>
                  <div className="rounded-lg border border-border bg-muted/30 px-4 py-3">
                    {optionsLoading ? (
                      <p className="text-sm text-muted-foreground">
                        Cargando opciones...
                      </p>
                    ) : selectedEntrada ? (
                      <div className="space-y-1">
                        <p className="font-semibold text-foreground">
                          {selectedEntrada.nombre}
                        </p>
                        {entradaPrice !== null && (
                          <div className="text-xs text-muted-foreground space-y-1">
                            <p>Precio actual: ${entradaPrice}</p>
                            {totalPrice !== null && (
                              <p>
                                Total por {formData.cantidad}: ${totalPrice}
                              </p>
                            )}
                          </div>
                        )}
                      </div>
                    ) : (
                      <p className="text-sm text-muted-foreground">
                        No hay una entrada anticipada configurada. Creá una en
                        la sección de configuración.
                      </p>
                    )}
                  </div>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="cantidad">Cantidad</Label>
                  <Input
                    id="cantidad"
                    type="number"
                    min={1}
                    value={formData.cantidad}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        cantidad: Math.max(1, Number(e.target.value)),
                      })
                    }
                    className="h-11"
                  />
                </div>
              </div>
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label>Evento</Label>
                  <Select
                    value={formData.eventoId}
                    onValueChange={(value) =>
                      setFormData({
                        ...formData,
                        eventoId: value === "none" ? "" : value,
                      })
                    }
                    disabled={optionsLoading}
                  >
                    <SelectTrigger className="h-11">
                      <SelectValue placeholder="Sin evento asignado" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">Sin evento asignado</SelectItem>
                      {eventos.map((evento) => (
                        <SelectItem key={evento.id} value={String(evento.id)}>
                          {evento.nombre}
                          {evento.fecha
                            ? ` — ${new Date(evento.fecha).toLocaleDateString(
                                "es-AR",
                                {
                                  month: "short",
                                  day: "numeric",
                                }
                              )}`
                            : ""}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex items-center justify-between gap-3 border border-border rounded-lg px-4 py-3">
                  <div>
                    <p className="font-medium">Incluye trago</p>
                    <p className="text-sm text-muted-foreground">
                      Agregá el beneficio al ticket impreso.
                    </p>
                  </div>
                  <Switch
                    checked={formData.incluyeTrago}
                    onCheckedChange={(checked) =>
                      setFormData({ ...formData, incluyeTrago: checked })
                    }
                  />
                </div>
              </div>

              <div className="flex gap-3 flex-col md:flex-row md:justify-end">
                <Button
                  className="w-full md:w-auto"
                  onClick={handleCreate}
                  disabled={creating || optionsLoading}
                >
                  {creating ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    "Registrar anticipada"
                  )}
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <Card className="border-border">
        <CardHeader className="pb-3">
          <CardTitle className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-lg font-semibold">Pendientes de impresión</p>
              <p className="text-sm text-muted-foreground">
                Lista de personas con compras anticipadas listas para entregar.
              </p>
            </div>
            <div className="relative w-full md:w-72">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Buscar por nombre, DNI o evento"
                className="pl-8"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
          </CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="flex items-center text-muted-foreground text-sm py-10">
              <Loader2 className="h-4 w-4 animate-spin mr-2" /> Cargando
              anticipadas...
            </div>
          ) : filteredAnticipadas.length === 0 ? (
            <p className="text-sm text-muted-foreground py-6">
              No hay anticipadas para mostrar.
            </p>
          ) : (
            <ScrollArea className="h-[480px]">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nombre</TableHead>
                    <TableHead>DNI</TableHead>
                    <TableHead>Entrada</TableHead>
                    <TableHead className="text-center">Cantidad</TableHead>
                    <TableHead>Evento</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredAnticipadas.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell>
                        <div className="font-semibold text-foreground">
                          {item.nombre}
                        </div>
                        <p className="text-xs text-muted-foreground">
                          Anticipada registrada
                        </p>
                      </TableCell>
                      <TableCell>{item.dni}</TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Badge variant="secondary">
                            {item.entradaNombre}
                          </Badge>
                          {item.incluyeTrago && (
                            <Badge variant="outline">+ Trago</Badge>
                          )}
                        </div>
                      </TableCell>
                      <TableCell className="text-center font-semibold">
                        {item.cantidad}
                      </TableCell>
                      <TableCell>{item.eventoNombre}</TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-2">
                          <Button
                            size="sm"
                            variant="secondary"
                            disabled={printingId === item.id}
                            onClick={() => handlePrint(item.id)}
                          >
                            {printingId === item.id ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              <Printer className="h-4 w-4" />
                            )}
                          </Button>
                          <Button
                            size="sm"
                            variant="destructive"
                            disabled={deletingId === item.id}
                            onClick={() => {
                              setDeleteTarget(item);
                              setDeleteDialogOpen(true);
                            }}
                          >
                            {deletingId === item.id ? (
                              <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                              <Trash2 className="h-4 w-4" />
                            )}
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </ScrollArea>
          )}
        </CardContent>
      </Card>
      <AlertDialog
        open={deleteDialogOpen}
        onOpenChange={(open) => {
          setDeleteDialogOpen(open);
          if (!open) {
            setDeleteTarget(null);
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Eliminar registro</AlertDialogTitle>
            <AlertDialogDescription>
              ¿Estás seguro de que deseas eliminar este registro? Esta acción es
              permanente.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deletingId !== null}>
              Cancelar
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={deletingId !== null}
            >
              {deletingId !== null ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                "Eliminar"
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
