import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { toast } from "@/hooks/use-toast";
import { api } from "@/services/api";
import { Loader2, Save } from "lucide-react";

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

interface PromotorCupo {
  id?: number | null;
  usuario_id: number;
  usuario_nombre: string;
  evento_id: number;
  entrada_id: number;
  cupo_total: number;
  cupo_vendido: number;
  cupo_disponible: number;
}

const normalizeEntradaName = (name: string) =>
  name
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();

export default function Promotores() {
  const [entradasAnticipadas, setEntradasAnticipadas] = useState<
    EntradaOption[]
  >([]);
  const [eventos, setEventos] = useState<EventoOption[]>([]);
  const [optionsLoading, setOptionsLoading] = useState(true);
  const [cuposLoading, setCuposLoading] = useState(false);
  const [promotorCupos, setPromotorCupos] = useState<PromotorCupo[]>([]);
  const [cupoEdits, setCupoEdits] = useState<Record<number, string>>({});
  const [savingAllCupos, setSavingAllCupos] = useState(false);
  const [selectedEventoId, setSelectedEventoId] = useState("");

  const fetchOptions = async () => {
    setOptionsLoading(true);
    try {
      const [entradasRes, eventosRes] = await Promise.all([
        api.get<EntradaOption[]>("/entradas"),
        api.get<EventoOption[]>("/eventos?upcoming=1"),
      ]);

      const anticipadasOptions = (entradasRes.data ?? []).filter(
        (entrada) => normalizeEntradaName(entrada.nombre) === "anticipada",
      );
      setEntradasAnticipadas(anticipadasOptions);

      const mappedEventos = (eventosRes.data ?? []).map((evento) => ({
        id: Number(evento.id),
        nombre: evento.nombre,
        fecha: evento.fecha,
      }));
      setEventos(mappedEventos);

      if (mappedEventos.length > 0) {
        setSelectedEventoId(String(mappedEventos[0].id));
      } else {
        setSelectedEventoId("");
      }
    } catch (error) {
      console.error("Error cargando opciones de promotores:", error);
      toast({
        title: "Error",
        description: "No se pudieron cargar los eventos o entradas.",
        variant: "destructive",
      });
    } finally {
      setOptionsLoading(false);
    }
  };

  const fetchPromotorCupos = async (eventoId: string, entradaId: number) => {
    if (!eventoId || !entradaId) {
      setPromotorCupos([]);
      return;
    }
    setCuposLoading(true);
    try {
      const { data } = await api.get<PromotorCupo[]>("/promotores_cupos", {
        params: {
          evento_id: Number(eventoId),
          entrada_id: entradaId,
        },
      });
      const cupos = data ?? [];
      setPromotorCupos(cupos);
      setCupoEdits((prev) => {
        const next = { ...prev };
        cupos.forEach((cupo) => {
          next[cupo.usuario_id] = String(cupo.cupo_total);
        });
        return next;
      });
    } catch (error) {
      console.error("Error cargando cupos de promotores:", error);
      toast({
        title: "Error",
        description: "No se pudieron cargar los cupos de promotores.",
        variant: "destructive",
      });
      setPromotorCupos([]);
    } finally {
      setCuposLoading(false);
    }
  };

  useEffect(() => {
    fetchOptions();
  }, []);

  const selectedEntrada = entradasAnticipadas[0] ?? null;
  const entradaPrice = selectedEntrada?.precio_base ?? null;

  useEffect(() => {
    if (!selectedEventoId || !selectedEntrada?.id) {
      setPromotorCupos([]);
      return;
    }
    fetchPromotorCupos(selectedEventoId, selectedEntrada.id);
  }, [selectedEventoId, selectedEntrada?.id]);

  const handleSaveAllCupos = async () => {
    if (!selectedEventoId || !selectedEntrada) {
      return;
    }
    if (promotorCupos.length === 0) {
      toast({
        title: "Sin promotores",
        description: "No hay cupos para actualizar.",
      });
      return;
    }
    const cuposPayload = promotorCupos.map((promotor) => {
      const cupoTotal = Number(cupoEdits[promotor.usuario_id]);
      return { promotor, cupoTotal };
    });
    const invalidCupo = cuposPayload.find(
      ({ cupoTotal }) => Number.isNaN(cupoTotal) || cupoTotal < 0,
    );
    if (invalidCupo) {
      toast({
        title: "Cupo inválido",
        description: "Ingresá un número válido para todos los cupos.",
        variant: "destructive",
      });
      return;
    }
    setSavingAllCupos(true);
    try {
      await Promise.all(
        cuposPayload.map(({ promotor, cupoTotal }) =>
          api.post("/promotores_cupos", {
            usuario_id: promotor.usuario_id,
            evento_id: Number(selectedEventoId),
            entrada_id: selectedEntrada.id,
            cupo_total: cupoTotal,
          }),
        ),
      );
      await fetchPromotorCupos(selectedEventoId, selectedEntrada.id);
      toast({
        title: "Cupos actualizados",
        description: "Se actualizaron los cupos de todos los promotores.",
      });
    } catch (error) {
      console.error("Error actualizando cupos de promotores:", error);
      toast({
        title: "No se pudo actualizar",
        description: "Reintentá en unos segundos.",
        variant: "destructive",
      });
    } finally {
      setSavingAllCupos(false);
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div>
        <h1 className="text-4xl font-bold text-foreground mb-2">Promotores</h1>
        <p className="text-muted-foreground">
          Administrá los cupos de anticipadas por promotor.
        </p>
      </div>

      <Card className="border-border">
        <CardHeader className="pb-3">
          <CardTitle className="text-lg font-semibold">
            Cupos de promotores
          </CardTitle>
          <p className="text-sm text-muted-foreground">
            Editá el cupo total por promotor para las anticipadas del evento
            seleccionado.
          </p>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label>Evento</Label>
              <Select
                value={selectedEventoId}
                onValueChange={(value) =>
                  setSelectedEventoId(value === "none" ? "" : value)
                }
                disabled={optionsLoading}
              >
                <SelectTrigger className="h-11">
                  <SelectValue placeholder="Seleccioná un evento" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Sin evento asignado</SelectItem>
                  {eventos.map((evento) => (
                    <SelectItem key={evento.id} value={String(evento.id)}>
                      {evento.nombre}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>Entrada anticipada</Label>
              <div className="rounded-lg border border-border bg-muted/30 px-4 py-3">
                {optionsLoading ? (
                  <p className="text-sm text-muted-foreground">
                    Cargando opciones...
                  </p>
                ) : selectedEntrada ? (
                  <div>
                    <p className="font-semibold text-foreground">
                      {selectedEntrada.nombre}
                    </p>
                    {entradaPrice !== null && (
                      <p className="text-xs text-muted-foreground">
                        Precio actual: ${entradaPrice}
                      </p>
                    )}
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">
                    No hay una entrada anticipada configurada.
                  </p>
                )}
              </div>
            </div>
          </div>

          {cuposLoading ? (
            <div className="flex items-center text-muted-foreground text-sm py-6">
              <Loader2 className="h-4 w-4 animate-spin mr-2" /> Cargando cupos
              de promotores...
            </div>
          ) : promotorCupos.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No hay promotores disponibles para este evento.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Promotor</TableHead>
                  <TableHead className="text-center">Cupo total</TableHead>
                  <TableHead className="text-center">Vendido</TableHead>
                  <TableHead className="text-center">Disponible</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {promotorCupos.map((promotor) => (
                  <TableRow key={promotor.usuario_id}>
                    <TableCell className="font-semibold text-foreground">
                      {promotor.usuario_nombre}
                    </TableCell>
                    <TableCell className="text-center">
                      <Input
                        className="h-9 text-center"
                        type="number"
                        min={0}
                        value={cupoEdits[promotor.usuario_id] ?? ""}
                        onChange={(event) =>
                          setCupoEdits((prev) => ({
                            ...prev,
                            [promotor.usuario_id]: event.target.value,
                          }))
                        }
                      />
                    </TableCell>
                    <TableCell className="text-center font-medium">
                      {promotor.cupo_vendido}
                    </TableCell>
                    <TableCell className="text-center font-medium">
                      {promotor.cupo_disponible}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
          {promotorCupos.length > 0 && !cuposLoading ? (
            <div className="flex justify-end">
              <Button
                size="sm"
                variant="secondary"
                disabled={
                  savingAllCupos ||
                  !selectedEventoId ||
                  !selectedEntrada ||
                  promotorCupos.length === 0
                }
                onClick={handleSaveAllCupos}
              >
                {savingAllCupos ? (
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                  <Save className="mr-2 h-4 w-4" />
                )}
                Guardar cambios
              </Button>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  );
}
