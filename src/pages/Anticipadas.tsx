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
  promotor_id?: number | null;
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

interface PromotorOptionResponse {
  id: number;
  nombre: string;
  telefono?: string | null;
  rol_nombre?: string | null;
}

interface VendedorOption {
  usuario_id: number;
  usuario_nombre: string;
  usuario_rol?: string | null;
  es_promotor: boolean;
  evento_id: number | null;
  entrada_id: number | null;
  cupo_total: number | null;
  cupo_vendido: number | null;
  cupo_disponible: number | null;
  tiene_cupo: boolean;
}

interface AnticipadasOptionsResponse {
  success?: boolean;
  entrada_anticipada?: EntradaOption | null;
  eventos?: EventoOption[];
  promotores?: PromotorOptionResponse[];
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

const getLoggedUserId = (): number | null => {
  const candidates = [
    localStorage.getItem("user_id"),
    localStorage.getItem("usuario_id"),
    localStorage.getItem("id"),
    localStorage.getItem("idUsuario"),
  ];

  for (const raw of candidates) {
    if (!raw) continue;

    const parsed = Number(raw);
    if (Number.isFinite(parsed) && parsed > 0) {
      return parsed;
    }
  }

  const jsonKeys = ["user", "usuario", "auth", "session", "authUser"];

  for (const key of jsonKeys) {
    const raw = localStorage.getItem(key);
    if (!raw) continue;

    try {
      const data = JSON.parse(raw) as {
        id?: number | string;
        user_id?: number | string;
        usuario_id?: number | string;
        idUsuario?: number | string;
        user?: {
          id?: number | string;
          user_id?: number | string;
          usuario_id?: number | string;
        };
      };

      const nestedCandidates = [
        data?.id,
        data?.user_id,
        data?.usuario_id,
        data?.idUsuario,
        data?.user?.id,
        data?.user?.user_id,
        data?.user?.usuario_id,
      ];

      for (const value of nestedCandidates) {
        const parsed = Number(value);
        if (Number.isFinite(parsed) && parsed > 0) {
          return parsed;
        }
      }
    } catch {
      // nada
    }
  }

  return null;
};

export default function Anticipadas(): JSX.Element {
  const [anticipadas, setAnticipadas] = useState<AnticipadaItem[]>([]);
  const [entradasAnticipadas, setEntradasAnticipadas] = useState<
    EntradaOption[]
  >([]);
  const [eventos, setEventos] = useState<EventoOption[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [optionsLoading, setOptionsLoading] = useState<boolean>(true);
  const [formOpen, setFormOpen] = useState<boolean>(false);
  const [creating, setCreating] = useState<boolean>(false);
  const [printingId, setPrintingId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<AnticipadaItem | null>(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState<boolean>(false);
  const [search, setSearch] = useState<string>("");
  const [vendedores, setVendedores] = useState<VendedorOption[]>([]);
  const [formData, setFormData] = useState({
    nombre: "",
    dni: "",
    entradaId: "",
    eventoId: "",
    promotorId: "",
    cantidad: 1,
    incluyeTrago: false,
  });

  const resetForm = (): void => {
    setFormData((prev) => ({
      ...prev,
      nombre: "",
      dni: "",
      promotorId: "",
      cantidad: 1,
      incluyeTrago: false,
    }));
  };

  const fetchAnticipadas = async (): Promise<void> => {
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

  const fetchOptions = async (): Promise<void> => {
    setOptionsLoading(true);

    try {
      const { data } = await api.get<AnticipadasOptionsResponse>(
        "/anticipadas",
        {
          params: { accion: "opciones" },
        },
      );

      const entradaAnticipada = data?.entrada_anticipada ?? null;
      const eventosData = Array.isArray(data?.eventos) ? data.eventos : [];
      const promotoresData = Array.isArray(data?.promotores)
        ? data.promotores
        : [];

      const anticipadasOptions: EntradaOption[] =
        entradaAnticipada &&
        normalizeEntradaName(entradaAnticipada.nombre) === "anticipada"
          ? [
              {
                id: Number(entradaAnticipada.id),
                nombre: entradaAnticipada.nombre,
                precio_base:
                  entradaAnticipada.precio_base !== undefined &&
                  entradaAnticipada.precio_base !== null
                    ? Number(entradaAnticipada.precio_base)
                    : null,
              },
            ]
          : [];

      const mappedEventos: EventoOption[] = eventosData.map((evento) => ({
        id: Number(evento.id),
        nombre: evento.nombre,
        fecha: evento.fecha ?? null,
      }));

      const mappedVendedores: VendedorOption[] = promotoresData.map(
        (promotor) => {
          const rol = promotor.rol_nombre ?? null;
          const rolNormalizado = (rol ?? "").trim().toLowerCase();

          return {
            usuario_id: Number(promotor.id),
            usuario_nombre: promotor.nombre,
            usuario_rol: rol,
            es_promotor: ["promotor", "promoter"].includes(rolNormalizado),
            evento_id: null,
            entrada_id: anticipadasOptions[0]?.id ?? null,
            cupo_total: null,
            cupo_vendido: null,
            cupo_disponible: null,
            tiene_cupo: false,
          };
        },
      );

      setEntradasAnticipadas(anticipadasOptions);
      setEventos(mappedEventos);
      setVendedores(mappedVendedores);

      setFormData((prev) => ({
        ...prev,
        entradaId:
          prev.entradaId ||
          (anticipadasOptions.length > 0
            ? String(anticipadasOptions[0].id)
            : ""),
        eventoId:
          prev.eventoId ||
          (mappedEventos.length > 0 ? String(mappedEventos[0].id) : ""),
        promotorId:
          prev.promotorId ||
          (mappedVendedores.length > 0
            ? String(mappedVendedores[0].usuario_id)
            : ""),
      }));
    } catch (error) {
      console.error("Error cargando opciones de anticipadas:", error);
      toast({
        title: "Error",
        description: "No se pudieron cargar las opciones de anticipadas.",
        variant: "destructive",
      });
    } finally {
      setOptionsLoading(false);
    }
  };

  useEffect(() => {
    void fetchAnticipadas();
    void fetchOptions();
  }, []);

  const selectedEntrada = entradasAnticipadas[0] ?? null;
  const entradaPrice = selectedEntrada?.precio_base ?? null;
  const totalPrice =
    entradaPrice !== null
      ? Math.max(1, formData.cantidad) * entradaPrice
      : null;

  const filteredAnticipadas = useMemo(() => {
    const query = search.trim().toLowerCase();

    if (!query) return anticipadas;

    return anticipadas.filter((item) =>
      `${item.nombre} ${item.dni} ${item.eventoNombre} ${item.entradaNombre}`
        .toLowerCase()
        .includes(query),
    );
  }, [anticipadas, search]);

  const handlePrint = async (itemId: number): Promise<void> => {
    setPrintingId(itemId);

    interface PrintJob {
      ticket_id: number;
      evento_id: number | null;
      entrada_id: number | null;
      usuario_id: number | null;
      tipo: string;
      precio: number;
      precio_formateado: string;
      incluye_trago: boolean;
      trago_texto: string;
      qr: string;
      estado: string;
      fecha: string;
      hora: string;
      negocio: string;
      ancho_papel: string;
      evento_fecha?: string;
      es_cortesia?: boolean;
      lista?: string;
    }

    interface ImprimirAnticipadaResponse {
      success?: boolean;
      mensaje?: string;
      id_eliminado?: number;
      entrada?: string;
      print_jobs?: PrintJob[];
    }

    interface PrinterResponse {
      ok?: boolean;
      mensaje?: string;
      error?: string;
    }

    try {
      const { data } = await api.post<ImprimirAnticipadaResponse>(
        "/anticipadas",
        {
          accion: "imprimir",
          id: itemId,
        },
      );

      const printJobs = Array.isArray(data?.print_jobs) ? data.print_jobs : [];

      if (printJobs.length === 0) {
        throw new Error("El backend no devolvió tickets para imprimir.");
      }

      const printerResponse = await fetch("http://localhost:3001/print", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          tickets: printJobs,
        }),
      });

      const printerData = (await printerResponse
        .json()
        .catch(() => null)) as PrinterResponse | null;

      if (!printerResponse.ok || printerData?.ok === false) {
        throw new Error(
          printerData?.error ??
            "No se pudo enviar el ticket al servicio de impresión.",
        );
      }

      setAnticipadas((prev) => prev.filter((item) => item.id !== itemId));

      toast({
        title: "Ticket impreso",
        description:
          printerData?.mensaje ??
          data?.mensaje ??
          "Ticket enviado a impresión.",
      });
    } catch (error) {
      console.error("Error al imprimir anticipada:", error);

      const message =
        error instanceof Error ? error.message : "Reintentá en unos segundos.";

      toast({
        title: "No se pudo imprimir",
        description: message,
        variant: "destructive",
      });
    } finally {
      setPrintingId(null);
    }
  };

  const handleCreate = async (): Promise<void> => {
    if (!formData.nombre.trim() || !formData.entradaId) {
      toast({
        title: "Datos incompletos",
        description: "Ingresá el nombre.",
        variant: "destructive",
      });
      return;
    }

    if (!formData.eventoId) {
      toast({
        title: "Evento requerido",
        description: "Seleccioná un evento para registrar la venta.",
        variant: "destructive",
      });
      return;
    }

    if (!formData.promotorId) {
      toast({
        title: "Vendedor requerido",
        description: "Seleccioná el vendedor que realiza la venta.",
        variant: "destructive",
      });
      return;
    }

    const usuarioId = getLoggedUserId();

    if (usuarioId === null) {
      toast({
        title: "Sesión inválida",
        description: "No se pudo obtener el usuario logueado.",
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
        promotor_id: Number(formData.promotorId),
        usuario_id: usuarioId,
        cantidad: formData.cantidad,
        incluye_trago: formData.incluyeTrago,
      });

      const nueva = data?.anticipada as AnticipadaResponse | undefined;

      if (nueva) {
        setAnticipadas((prev) => [mapAnticipada(nueva), ...prev]);

        toast({
          title: "Anticipada registrada",
          description: (data?.mensaje as string) ?? "Se agregó al listado.",
        });

        resetForm();
        setFormOpen(false);
      }
    } catch (error) {
      console.error("Error al registrar anticipada:", error);

      const apiMessage =
        (error as { response?: { data?: { error?: string } } })?.response?.data
          ?.error ?? "Revisá los datos e intentá nuevamente.";

      toast({
        title: "No se pudo registrar",
        description: apiMessage,
        variant: "destructive",
      });
    } finally {
      setCreating(false);
    }
  };

  const handleDelete = async (): Promise<void> => {
    if (!deleteTarget) return;

    setDeletingId(deleteTarget.id);

    try {
      await api.post("/anticipadas", {
        accion: "eliminar",
        id: deleteTarget.id,
      });

      setAnticipadas((prev) =>
        prev.filter((anticipada) => anticipada.id !== deleteTarget.id),
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

  const selectedVendedor = vendedores.find(
    (vendedor) => String(vendedor.usuario_id) === formData.promotorId,
  );

  const selectedVendedorEsPromotor = selectedVendedor?.es_promotor === true;

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
                                },
                              )}`
                            : ""}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label>Vendedor</Label>
                  <Select
                    value={formData.promotorId}
                    onValueChange={(value) =>
                      setFormData({
                        ...formData,
                        promotorId: value,
                      })
                    }
                    disabled={optionsLoading || vendedores.length === 0}
                  >
                    <SelectTrigger className="h-11">
                      <SelectValue placeholder="Seleccioná un vendedor" />
                    </SelectTrigger>

                    <SelectContent>
                      {vendedores.map((vendedor) => (
                        <SelectItem
                          key={vendedor.usuario_id}
                          value={String(vendedor.usuario_id)}
                        >
                          {vendedor.usuario_nombre}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>

                  {selectedVendedor &&
                    selectedVendedorEsPromotor &&
                    selectedVendedor.tiene_cupo === true &&
                    typeof selectedVendedor.cupo_disponible === "number" && (
                      <p className="text-xs text-muted-foreground">
                        Cupo disponible: {selectedVendedor.cupo_disponible}
                      </p>
                    )}
                </div>
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
                            onClick={() => {
                              void handlePrint(item.id);
                            }}
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
              onClick={() => {
                void handleDelete();
              }}
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
