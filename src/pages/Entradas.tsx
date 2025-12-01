import React, { useEffect, useMemo, useState } from "react";
import {
  Calendar,
  CheckCircle,
  Loader2,
  MinusCircle,
  Printer,
  Ticket,
} from "lucide-react";
import { StatCounter } from "@/components/StatCounter";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Card } from "@/components/ui/card";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { useToast } from "@/hooks/use-toast";

import { api } from "@/services/api";
import { imprimirTicket } from "@/services/printer";

import { printEntradaTicket } from "@/lib/qzTray";

import { connectPrinter, printTicket } from "@/lib/printTicket";

interface EventOption {
  id: number;
  name: string;
  capacity: number;
  date: string | null;
}

interface EntradaOption {
  id: number;
  nombre: string;
  descripcion: string | null;
  precio_base: number;
}

interface VentaResumen {
  evento_id: number | null;
  entrada_id: number;
  total_vendido: number;
}

interface VentaEntradasResponse {
  eventos: {
    id: number;
    nombre: string;
    fecha: string | null;
    capacidad: number;
  }[];
  entradas: {
    id: number;
    nombre: string;
    descripcion: string | null;
    precio_base: number;
  }[];
  ventas: VentaResumen[];
}

type StatCounterVariant = "primary" | "accent" | "success" | "default";

type VentasPorEvento = Record<string, Record<number, number>>;

export default function Entradas() {
  const { toast } = useToast();
  const [selectedEvent, setSelectedEvent] = useState<string>("");
  const [events, setEvents] = useState<EventOption[]>([]);
  const [entradas, setEntradas] = useState<EntradaOption[]>([]);
  const [ventasPorEvento, setVentasPorEvento] = useState<VentasPorEvento>({});
  const [loading, setLoading] = useState<boolean>(true);
  const [showSummary, setShowSummary] = useState<boolean>(false);
  const [sellingEntradaId, setSellingEntradaId] = useState<number | null>(null);
  const [cantidadVenta, setCantidadVenta] = useState<number>(1);
  const [incluyeTrago, setIncluyeTrago] = useState<boolean>(false);
  const [registrandoVenta, setRegistrandoVenta] = useState<boolean>(false);
  const [tipoOperacion, setTipoOperacion] = useState<"venta" | "resta">(
    "venta"
  );
  const [closingEvent, setClosingEvent] = useState<boolean>(false);

  const numberFormatter = useMemo(
    () => new Intl.NumberFormat("es-AR", { maximumFractionDigits: 0 }),
    []
  );

  const currencyFormatter = useMemo(
    () =>
      new Intl.NumberFormat("es-AR", {
        style: "currency",
        currency: "ARS",
        maximumFractionDigits: 0,
      }),
    []
  );

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const { data } = await api.get<VentaEntradasResponse>(
          "/venta_entradas.php"
        );

        const mappedEvents = data.eventos.map((evento) => ({
          id: evento.id,
          name: evento.nombre,
          capacity: evento.capacidad,
          date: evento.fecha,
        }));

        const mappedEntradas = data.entradas.map((entrada) => ({
          id: entrada.id,
          nombre: entrada.nombre,
          descripcion: entrada.descripcion,
          precio_base: Number(entrada.precio_base),
        }));

        const ventasMap: VentasPorEvento = {};
        data.ventas.forEach((venta) => {
          const eventKey =
            venta.evento_id !== null ? String(venta.evento_id) : "sin_evento";
          if (!ventasMap[eventKey]) {
            ventasMap[eventKey] = {};
          }
          ventasMap[eventKey][venta.entrada_id] = Number(
            venta.total_vendido ?? 0
          );
        });

        setEvents(mappedEvents);
        setEntradas(mappedEntradas);
        setVentasPorEvento(ventasMap);

        if (mappedEvents.length > 0) {
          setSelectedEvent((prev) => prev || String(mappedEvents[0].id));
        }
      } catch (error) {
        console.error("Error al cargar venta de entradas:", error);
        toast({
          title: "Error",
          description: "No se pudieron cargar los eventos y entradas.",
          variant: "destructive",
        });
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [toast]);

  const selectedEventData = events.find(
    (event) => String(event.id) === selectedEvent
  );
  const currentSales = selectedEvent
    ? ventasPorEvento[selectedEvent] ?? {}
    : {};

  const totalEntradas = Object.values(currentSales).reduce(
    (acc, value) => acc + value,
    0
  );

  const maxCapacity = selectedEventData?.capacity ?? 0;
  const capacidadPorcentaje =
    maxCapacity > 0 ? Math.round((totalEntradas / maxCapacity) * 100) : null;
  const totalRecaudado = entradas.reduce((acc, entrada) => {
    const cantidad = currentSales[entrada.id] ?? 0;
    return acc + cantidad * entrada.precio_base;
  }, 0);
  const variants: StatCounterVariant[] = ["primary", "accent", "success"];

  const handleCloseEvent = () => {
    if (!selectedEvent) {
      toast({
        title: "Seleccioná un evento",
        description: "Debes elegir un evento antes de cerrarlo.",
      });
      return;
    }
    setShowSummary(true);
  };

  const handleConfirmClose = () => {
    if (!selectedEvent || !selectedEventData) {
      toast({
        title: "Seleccioná un evento",
        description: "Debes elegir un evento antes de cerrarlo.",
        variant: "destructive",
      });
      return;
    }

    setClosingEvent(true);

    api
      .post("/venta_entradas.php", {
        accion: "cerrar_evento",
        evento_id: Number(selectedEvent),
      })
      .then(() => {
        toast({
          title: "Evento cerrado",
          description: "Se guardó el resumen y se reiniciaron los contadores.",
        });
        const closedId = Number(selectedEvent);
        setVentasPorEvento((prev) => {
          const updated = { ...prev };
          delete updated[selectedEvent];
          return updated;
        });
        setEvents((prev) => {
          const filtered = prev.filter((event) => event.id !== closedId);
          if (!filtered.some((event) => String(event.id) === selectedEvent)) {
            setSelectedEvent(filtered.length > 0 ? String(filtered[0].id) : "");
          }
          return filtered;
        });
        setShowSummary(false);
      })
      .catch((error) => {
        console.error("Error al cerrar evento:", error);
        toast({
          title: "No se pudo cerrar el evento",
          description: "Reintentá en unos segundos.",
          variant: "destructive",
        });
      })
      .finally(() => {
        setClosingEvent(false);
      });
  };

  const abrirVenta = (entradaId: number) => {
    if (!selectedEvent) {
      toast({
        title: "Seleccioná un evento",
        description: "Debes elegir un evento antes de registrar una venta.",
        variant: "destructive",
      });
      return;
    }

    setSellingEntradaId(entradaId);
    setCantidadVenta(1);
    setIncluyeTrago(false);
    setTipoOperacion("venta");
  };

  const abrirResta = (entradaId: number) => {
    if (!selectedEvent) {
      toast({
        title: "Seleccioná un evento",
        description: "Debes elegir un evento antes de ajustar una venta.",
        variant: "destructive",
      });
      return;
    }

    const disponibles = currentSales[entradaId] ?? 0;

    if (disponibles <= 0) {
      toast({
        title: "Sin entradas registradas",
        description: "No hay ventas para restar en este tipo de entrada.",
      });
      return;
    }

    setSellingEntradaId(entradaId);
    setCantidadVenta(1);
    setIncluyeTrago(false);
    setTipoOperacion("resta");
  };

  const cerrarVenta = () => {
    setSellingEntradaId(null);
    setCantidadVenta(1);
    setIncluyeTrago(false);
    setTipoOperacion("venta");
  };

  const registrarVenta = async () => {
    if (!selectedEvent || sellingEntradaId === null) {
      toast({
        title: "Datos incompletos",
        description:
          "Seleccioná un evento y un tipo de entrada para continuar.",
        variant: "destructive",
      });
      return;
    }

    if (!Number.isFinite(cantidadVenta) || cantidadVenta <= 0) {
      toast({
        title: "Cantidad inválida",
        description: "La cantidad debe ser al menos 1.",
        variant: "destructive",
      });
      return;
    }

    const entrada = entradas.find((item) => item.id === sellingEntradaId);
    if (!entrada) {
      toast({
        title: "Entrada no encontrada",
        description: "No se pudo encontrar el tipo de entrada seleccionado.",
        variant: "destructive",
      });
      return;
    }

    const disponibles = currentSales[sellingEntradaId] ?? 0;
    if (tipoOperacion === "resta" && cantidadVenta > disponibles) {
      toast({
        title: "Cantidad inválida",
        description: "No podés restar más entradas de las registradas.",
        variant: "destructive",
      });
      return;
    }

    setRegistrandoVenta(true);

    try {
      const payload = {
        accion: tipoOperacion === "resta" ? "restar" : "registrar",
        evento_id: Number(selectedEvent),
        entrada_id: sellingEntradaId,
        cantidad: cantidadVenta,
        incluye_trago: tipoOperacion === "venta" ? incluyeTrago : false,
      };

      const { data } = await api.post("/venta_entradas.php", payload);
      const eventKey =
        data?.evento_id !== undefined && data?.evento_id !== null
          ? String(data.evento_id)
          : String(selectedEvent);
      const entradaKey = data?.entrada_id ?? sellingEntradaId;
      const cantidadRegistrada =
        typeof data?.cantidad === "number"
          ? Number(data.cantidad)
          : tipoOperacion === "resta"
          ? -cantidadVenta
          : cantidadVenta;

      setVentasPorEvento((prev) => {
        const previousEventSales = prev[eventKey] ?? {};
        const updatedEventSales = { ...previousEventSales };
        const nuevoTotal = Math.max(
          (previousEventSales[entradaKey] ?? 0) + cantidadRegistrada,
          0
        );

        if (nuevoTotal > 0) {
          updatedEventSales[entradaKey] = nuevoTotal;
        } else {
          delete updatedEventSales[entradaKey];
        }
        return {
          ...prev,
          [eventKey]: updatedEventSales,
        };
      });

      const cantidadAbsoluta = Math.abs(cantidadRegistrada);

      toast({
        title:
          tipoOperacion === "resta" ? "Ajuste registrado" : "Venta registrada",
        description: `${cantidadAbsoluta} ${
          cantidadAbsoluta === 1 ? "entrada" : "entradas"
        } de ${entrada.nombre} ${
          tipoOperacion === "resta" ? "restadas" : "registradas"
        } correctamente.`,
      });

      if (tipoOperacion === "venta") {
        try {
          await imprimirTicket({
            evento: selectedEventData?.name,
            entrada: entrada.nombre,
            cantidad: cantidadAbsoluta,
            incluyeTrago,
          });
        } catch (printError) {
          console.error("Error al imprimir:", printError);
          toast({
            title: "No se pudo imprimir",
            description: "Verificá QZ Tray y la conexión con la impresora.",
            variant: "destructive",
          });
        }
      }

      cerrarVenta();
    } catch (error) {
      console.error("Error al registrar venta:", error);
      toast({
        title: "No se pudo registrar la venta",
        description: "Reintentá en unos segundos.",
        variant: "destructive",
      });
    } finally {
      setRegistrandoVenta(false);
    }
  };

  const ventaAbierta = sellingEntradaId !== null;
  const entradaSeleccionada = entradas.find(
    (item) => item.id === sellingEntradaId
  );
  const maxRestante =
    sellingEntradaId !== null ? currentSales[sellingEntradaId] ?? 0 : 0;
  const totalOperacion = entradaSeleccionada
    ? entradaSeleccionada.precio_base * cantidadVenta
    : 0;

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">
            Control de Entradas - Santas
          </h1>
          <p className="text-muted-foreground">
            Gestión en tiempo real de ventas por evento
          </p>
        </div>
      </div>

      <div className="flex flex-col sm:flex-row items-center gap-4">
        <div className="flex items-center gap-2">
          <Calendar className="text-primary" />
          <span className="font-medium text-foreground">Evento:</span>
        </div>
        <Select
          value={selectedEvent}
          onValueChange={(value) => setSelectedEvent(value)}
        >
          <SelectTrigger className="w-[260px]">
            <SelectValue placeholder="Seleccionar evento" />
          </SelectTrigger>
          <SelectContent>
            {events.map((event) => (
              <SelectItem key={event.id} value={String(event.id)}>
                {event.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="bg-card border border-border rounded-lg p-6 mb-6">
        {loading ? (
          <div className="flex items-center text-muted-foreground text-sm">
            <Loader2 className="h-4 w-4 animate-spin mr-2" /> Cargando...
          </div>
        ) : selectedEvent ? (
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-sm text-muted-foreground uppercase tracking-wide">
                Ventas Totales
              </p>
              <p className="text-5xl font-bold text-primary">
                {numberFormatter.format(totalEntradas)}
              </p>
              <div className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                <Ticket className="h-4 w-4" />
                Entradas vendidas para {selectedEventData?.name ?? "el evento"}
              </div>
            </div>
            <div className="text-left md:text-right">
              <p className="text-sm text-muted-foreground">Capacidad Máxima</p>
              <p className="text-2xl font-semibold text-foreground">
                {maxCapacity > 0 ? numberFormatter.format(maxCapacity) : "—"}
              </p>
              {capacidadPorcentaje !== null && (
                <p className="text-sm text-muted-foreground mt-1">
                  Ocupación:{" "}
                  <span className="font-semibold text-foreground">
                    {capacidadPorcentaje}%
                  </span>
                </p>
              )}
              <div className="mt-2">
                <div className="w-full md:w-48 h-3 bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full bg-gradient-primary transition-all duration-500"
                    style={{
                      width: maxCapacity
                        ? `${Math.min(
                            Math.max(capacidadPorcentaje ?? 0, 0),
                            100
                          )}%`
                        : "0%",
                    }}
                  />
                </div>
              </div>
              <div className="mt-3 text-sm text-muted-foreground">
                Total recaudado:{" "}
                <span className="font-semibold text-foreground">
                  {currencyFormatter.format(totalRecaudado)}
                </span>
              </div>
            </div>
          </div>
        ) : (
          <p className="text-muted-foreground text-sm italic">
            Seleccione un evento para comenzar
          </p>
        )}
      </div>

      {selectedEvent && (
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          {entradas.map((entrada, index) => (
            <StatCounter
              key={entrada.id}
              title={entrada.nombre}
              subtitle={entrada.descripcion ?? undefined}
              count={currentSales[entrada.id] ?? 0}
              variant={variants[index % variants.length]}
              actionLabel="Vender entrada"
              onAction={() => abrirVenta(entrada.id)}
              priceLabel={currencyFormatter.format(entrada.precio_base)}
              secondaryActionLabel="Restar entradas"
              onSecondaryAction={() => abrirResta(entrada.id)}
              secondaryDisabled={(currentSales[entrada.id] ?? 0) === 0}
            />
          ))}
        </div>
      )}

      {selectedEvent && (
        <div className="flex justify-center mt-8">
          <Button
            onClick={handleCloseEvent}
            disabled={!selectedEvent}
            className="px-12 py-6 bg-[#0f5132] text-white text-lg font-semibold hover:bg-[#0c452a]"
          >
            <CheckCircle className="mr-2 h-5 w-5" />
            Cerrar Evento
          </Button>
        </div>
      )}

      <Dialog open={showSummary} onOpenChange={setShowSummary}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Resumen del Evento</DialogTitle>
          </DialogHeader>

          <div className="space-y-3 mt-4 text-sm text-muted-foreground">
            <p>
              <strong>Evento:</strong> {selectedEventData?.name}
            </p>
            <p>
              <strong>Total vendidas:</strong>{" "}
              {numberFormatter.format(totalEntradas)}
            </p>
            <p>
              <strong>Total recaudado:</strong>{" "}
              {currencyFormatter.format(totalRecaudado)}
            </p>
            {capacidadPorcentaje !== null && (
              <p>
                <strong>Ocupación:</strong> {capacidadPorcentaje}%
              </p>
            )}
            <div className="pt-2 space-y-1">
              <p className="font-semibold text-foreground">
                Detalle por entrada:
              </p>
              {entradas.map((entrada) => {
                const cantidad = currentSales[entrada.id] ?? 0;
                if (!cantidad) {
                  return null;
                }
                const totalEntrada = cantidad * entrada.precio_base;
                return (
                  <div
                    key={entrada.id}
                    className="flex items-center justify-between"
                  >
                    <span>{entrada.nombre}</span>
                    <div className="text-right">
                      <span className="block">
                        {numberFormatter.format(cantidad)}{" "}
                        {cantidad === 1 ? "entrada" : "entradas"}
                      </span>
                      <span className="text-xs text-muted-foreground">
                        {currencyFormatter.format(totalEntrada)}
                      </span>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <DialogFooter className="mt-6">
            <Button variant="outline" onClick={() => setShowSummary(false)}>
              Cancelar
            </Button>
            <Button
              onClick={handleConfirmClose}
              className="bg-[#0f5132] text-white hover:bg-[#0c452a]"
              disabled={closingEvent}
            >
              {closingEvent ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin mr-2" /> Guardando...
                </>
              ) : (
                "Confirmar Cierre"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog
        open={ventaAbierta}
        onOpenChange={(open) => {
          if (!open) {
            cerrarVenta();
          }
        }}
      >
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>
              {tipoOperacion === "resta"
                ? "Restar entradas"
                : "Registrar venta"}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-4 mt-2">
            <div>
              <p className="text-sm text-muted-foreground">
                Evento seleccionado
              </p>
              <p className="font-semibold text-foreground">
                {selectedEventData?.name ?? "Sin evento"}
              </p>
            </div>

            <div>
              <p className="text-sm text-muted-foreground">Tipo de entrada</p>
              <p className="font-semibold text-foreground">
                {entradaSeleccionada?.nombre ?? "Sin selección"}
              </p>
              {entradaSeleccionada && (
                <p className="text-sm text-muted-foreground">
                  Precio base:{" "}
                  {currencyFormatter.format(entradaSeleccionada.precio_base)}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="cantidad">
                Cantidad {tipoOperacion === "resta" ? "a restar" : "a vender"}
              </Label>
              <Input
                id="cantidad"
                type="number"
                min={1}
                max={
                  tipoOperacion === "resta"
                    ? Math.max(maxRestante, 1)
                    : undefined
                }
                value={cantidadVenta}
                onChange={(event) => {
                  const value = Number(event.target.value);
                  if (Number.isNaN(value)) {
                    setCantidadVenta(1);
                  } else {
                    const baseValue = value <= 0 ? 1 : Math.floor(value);
                    if (tipoOperacion === "resta") {
                      const limite = Math.max(maxRestante, 1);
                      setCantidadVenta(
                        limite > 0 ? Math.min(baseValue, limite) : 1
                      );
                    } else {
                      setCantidadVenta(baseValue);
                    }
                  }
                }}
              />
            </div>
            {tipoOperacion === "resta" && (
              <p className="text-xs text-muted-foreground">
                Disponibles para restar: {numberFormatter.format(maxRestante)}
              </p>
            )}
            {tipoOperacion === "venta" && (
              <div className="flex items-center space-x-2">
                <Checkbox
                  id="incluye-trago"
                  checked={incluyeTrago}
                  onCheckedChange={(checked) =>
                    setIncluyeTrago(Boolean(checked))
                  }
                />
                <Label htmlFor="incluye-trago">Incluye trago gratis</Label>
              </div>
            )}
            <div className="rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
              <p className="font-semibold text-foreground">
                Total {tipoOperacion === "resta" ? "a descontar" : "a cobrar"}:{" "}
                <span className="ml-1">
                  {currencyFormatter.format(
                    tipoOperacion === "resta" ? -totalOperacion : totalOperacion
                  )}
                </span>
              </p>
            </div>
          </div>

          <DialogFooter className="mt-6">
            <Button
              variant="outline"
              onClick={cerrarVenta}
              disabled={registrandoVenta}
            >
              Cancelar
            </Button>
            <Button
              onClick={registrarVenta}
              disabled={
                registrandoVenta ||
                (tipoOperacion === "resta" && maxRestante <= 0)
              }
            >
              {registrandoVenta ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin mr-2" />{" "}
                  Registrando...
                </>
              ) : (
                <>
                  {tipoOperacion === "resta" ? (
                    <>
                      <MinusCircle className="h-4 w-4 mr-2" /> Restar
                    </>
                  ) : (
                    <>
                      <Printer className="h-4 w-4 mr-2" /> Imprimir
                    </>
                  )}
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
