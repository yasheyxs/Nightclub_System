import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Calendar, Users, Plus, Loader2, Pencil, Trash2 } from "lucide-react";
import { toast } from "@/hooks/use-toast";
import { api } from "@/services/api";
import CalendarView from "react-calendar";
import "react-calendar/dist/Calendar.css";

interface Event {
  id: number;
  nombre: string;
  detalle: string;
  fecha: string; // ISO (yyyy-mm-dd o yyyy-mm-ddTHH:mm:ss)
  capacidad: number;
}

export default function Eventos() {
  const [events, setEvents] = useState<Event[]>([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Event | null>(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Event | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const [form, setForm] = useState({
    nombre: "",
    detalle: "",
    fecha: "",
    capacidad: "1000",
  });

  // ---- Helpers ----
  const onlyFutureSorted = useCallback((list: Event[]) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return list
      .filter((e) => {
        const d = new Date(e.fecha);
        d.setHours(0, 0, 0, 0);
        return d >= today;
      })
      .sort(
        (a, b) => new Date(a.fecha).getTime() - new Date(b.fecha).getTime()
      );
  }, []);

  const findByDate = (date: Date) => {
    const target = new Date(date);
    target.setHours(0, 0, 0, 0);
    return (
      events.find((e) => {
        const d = new Date(e.fecha);
        d.setHours(0, 0, 0, 0);
        return d.getTime() === target.getTime();
      }) || null
    );
  };

  // ---- Fetch inicial ----
  useEffect(() => {
    const fetchEvents = async () => {
      setLoading(true);
      try {
        const { data } = await api.get<Event[]>("/eventos.php?calendar=1");
        setEvents(onlyFutureSorted(data));
      } catch (error) {
        console.error(error);
        toast({
          title: "Error",
          description: "No se pudieron cargar los eventos.",
          variant: "destructive",
        });
      } finally {
        setLoading(false);
      }
    };
    fetchEvents();
  }, [onlyFutureSorted]);

  useEffect(() => {
    const prune = () => {
      setEvents((prev) => {
        const next = onlyFutureSorted([...prev]);
        if (
          next.length === prev.length &&
          next.every((event, index) => event.id === prev[index].id)
        ) {
          return prev;
        }
        return next;
      });
    };

    prune();
    const interval = setInterval(prune, 1000 * 60 * 30);
    return () => clearInterval(interval);
  }, [onlyFutureSorted]);

  // ---- Guardar (crear/editar) ----
  const handleSave = async () => {
    if (!form.nombre || !form.fecha || !form.capacidad) {
      toast({
        title: "Campos obligatorios",
        description: "Completá nombre, fecha y capacidad.",
        variant: "destructive",
      });
      return;
    }

    try {
      if (editing) {
        const { data } = await api.put<Event>(`/eventos.php?id=${editing.id}`, {
          nombre: form.nombre,
          detalle: form.detalle,
          capacidad: parseInt(form.capacidad, 10),
        });
        setEvents((prev) =>
          onlyFutureSorted(prev.map((e) => (e.id === data.id ? data : e)))
        );
        toast({
          title: "Evento actualizado",
          description: "Cambios guardados.",
        });
      } else {
        const { data } = await api.post<Event>("/eventos.php", {
          nombre: form.nombre,
          detalle: form.detalle,
          fecha: form.fecha,
          capacidad: parseInt(form.capacidad, 10),
        });
        setEvents((prev) => onlyFutureSorted([...prev, data]));
        toast({
          title: "Evento creado",
          description: "Se agregó correctamente.",
        });
      }

      setOpen(false);
      setEditing(null);
      setForm({ nombre: "", detalle: "", fecha: "", capacidad: "" });
    } catch (error) {
      console.error(error);
      toast({
        title: "Error",
        description: "No se pudo guardar el evento.",
        variant: "destructive",
      });
    }
  };

  // ---- Eliminar ----
  const requestDelete = (event: Event) => {
    setDeleteTarget(event);
    setDeleteOpen(true);
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleteLoading(true);
    try {
      await api.delete(`/eventos.php?id=${deleteTarget.id}`);
      setEvents((prev) =>
        onlyFutureSorted(prev.filter((e) => e.id !== deleteTarget.id))
      );
      toast({
        title: "Evento eliminado",
        description: "Se eliminó correctamente.",
      });
      setDeleteOpen(false);
      setDeleteTarget(null);
    } catch (error) {
      toast({
        title: "Error",
        description: "No se pudo eliminar el evento.",
        variant: "destructive",
      });
    } finally {
      setDeleteLoading(false);
    }
  };

  // ---- Doble click en día (sin any / sin globals) ----
  const lastClickRef = useRef<{ date: Date; time: number } | null>(null);
  const onCalendarDayClick = (date: Date) => {
    const now = Date.now();
    const last = lastClickRef.current;
    const sameDay = last && last.date.toDateString() === date.toDateString();
    const isDouble = sameDay && now - (last?.time ?? 0) < 400;

    if (isDouble) {
      const existing = findByDate(date);

      if (existing) {
        setEditing(existing);
        setForm({
          nombre: existing.nombre,
          detalle: existing.detalle,
          fecha: existing.fecha.split("T")[0],
          capacidad: String(existing.capacidad),
        });
        toast({
          title: "Editando evento",
          description: `“${existing.nombre}”`,
        });
      } else {
        const iso = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
          .toISOString()
          .slice(0, 10);
        setEditing(null);
        setForm({ nombre: "", detalle: "", fecha: iso, capacidad: "" });
        toast({
          title: "Nueva fecha",
          description: `Creá un evento para el ${date.toLocaleDateString(
            "es-AR"
          )}`,
        });
      }
      setOpen(true);
    }

    lastClickRef.current = { date, time: now };
  };

  // ---- Derivados ----
  const upcomingEvent = events[0] ?? null;

  const eventsCount = useMemo(() => events.length, [events]);

  const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString("es-AR", {
      weekday: "long",
      day: "numeric",
      month: "long",
    });

  if (loading) {
    return (
      <div className="flex justify-center py-20 text-muted-foreground">
        <Loader2 className="h-5 w-5 animate-spin mr-2" /> Cargando eventos...
      </div>
    );
  }

  return (
    <div className="space-y-8 animate-fade-in">
      {/* Header */}
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <h1 className="text-4xl font-bold text-foreground">Próximos Eventos</h1>
        <Button
          size="lg"
          className="gap-2"
          onClick={() => {
            setEditing(null);
            setForm({ nombre: "", detalle: "", fecha: "", capacidad: "" });
            setOpen(true);
          }}
        >
          <Plus className="h-5 w-5" />
          <span className="hidden lg:inline">Nuevo Evento</span>
        </Button>
      </div>

      {/* Calendario (dark + marcado de eventos) */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Calendar className="h-5 w-5 text-primary" />
            Calendario de próximos eventos
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border border-border bg-neutral-900 text-neutral-100 p-3 shadow-sm select-none">
            <CalendarView
              className="w-full dark-calendar"
              locale="es-AR"
              onClickDay={onCalendarDayClick}
              tileClassName={({ date }) => {
                const e = findByDate(date);
                if (!e) return "";
                return e.detalle ? "event-purple" : "event-blue";
              }}
              tileContent={({ date }) => {
                const e = findByDate(date);
                return e ? (
                  <div
                    className={`mt-1 h-1 w-1 mx-auto rounded-full ${
                      e.detalle ? "bg-purple-400" : "bg-blue-400"
                    }`}
                  />
                ) : null;
              }}
            />
          </div>

          <p className="text-xs text-muted-foreground mt-3 flex items-center gap-4">
            <span className="flex items-center gap-1">
              <span className="inline-block w-3 h-3 bg-blue-500 rounded-sm" />
              Evento regular
            </span>
            <span className="flex items-center gap-1">
              <span className="inline-block w-3 h-3 bg-purple-500 rounded-sm" />
              Evento con detalle / DJ
            </span>
          </p>
        </CardContent>
      </Card>

      {/* Estilos del calendario (modo oscuro y sin “blanco” al click) */}
      <style
        dangerouslySetInnerHTML={{
          __html: `
          .dark-calendar {
            background-color: #111827;
            color: #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.95rem;
          }
          .dark-calendar .react-calendar__navigation {
            background: transparent !important;
          }
          .dark-calendar .react-calendar__navigation button {
            color: #f3f4f6 !important;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease, color 0.2s ease;
          }
          .dark-calendar .react-calendar__navigation button:enabled:hover {
            background-color: rgba(59, 130, 246, 0.15) !important;
            color: #93c5fd !important;
          }
          .dark-calendar .react-calendar__navigation button:enabled:active {
            background-color: rgba(59, 130, 246, 0.25) !important;
            color: #bfdbfe !important;
          }
          .dark-calendar .react-calendar__tile {
            padding: 0.8rem 0;
            border-radius: 0.6rem;
            transition: all 0.2s ease;
            background: transparent !important;
            position: relative;
          }
          .dark-calendar .react-calendar__tile:hover {
            background-color: rgba(59, 130, 246, 0.15) !important;
          }
          .dark-calendar .react-calendar__tile--now {
            background-color: rgba(147, 51, 234, 0.15) !important;
            border-radius: 9999px;
          }
          .dark-calendar button:focus {
            background: transparent !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4);
          }
          .dark-calendar .event-blue {
            background-color: rgba(37, 99, 235, 0.4) !important;
            color: #fff !important;
            border-radius: 0.5rem;
          }
          .dark-calendar .event-blue:hover {
            background-color: rgba(59, 130, 246, 0.5) !important;
          }
          .dark-calendar .event-purple {
            background-color: rgba(126, 34, 206, 0.4) !important;
            color: #fff !important;
            border-radius: 0.5rem;
          }
          .dark-calendar .event-purple:hover {
            background-color: rgba(147, 51, 234, 0.55) !important;
          }
          .dark-calendar .react-calendar__month-view__days__day--neighboringMonth {
            opacity: 0.25;
          }
        `,
        }}
      />

      {/* Resumen */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">
              Total Eventos
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{eventsCount}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">
              Capacidad próximo evento
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-accent">
              {upcomingEvent ? upcomingEvent.capacidad : "-"}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Listado (solo futuros, ordenados) */}
      <div className="space-y-4">
        {eventsCount === 0 ? (
          <p className="text-muted-foreground text-sm">
            No hay eventos próximos.
          </p>
        ) : (
          events.map((event) => (
            <Card
              key={event.id}
              className="border hover:shadow-md transition-all duration-200"
            >
              <CardContent className="p-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div className="flex-1 space-y-2">
                  <h3 className="text-2xl font-bold text-foreground">
                    {event.nombre}
                  </h3>
                  {event.detalle && (
                    <p className="text-muted-foreground text-sm leading-relaxed">
                      {event.detalle}
                    </p>
                  )}
                  <div className="flex flex-wrap gap-4 text-muted-foreground mt-2">
                    <div className="flex items-center gap-2">
                      <Calendar className="h-4 w-4 text-primary" />
                      <span className="text-sm capitalize">
                        {formatDate(event.fecha)}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Users className="h-4 w-4 text-accent" />
                      <span className="text-sm">
                        Capacidad: {event.capacidad} personas
                      </span>
                    </div>
                  </div>
                </div>

                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    onClick={() => {
                      setEditing(event);
                      setForm({
                        nombre: event.nombre,
                        detalle: event.detalle,
                        fecha: event.fecha.split("T")[0],
                        capacidad: String(event.capacidad),
                      });
                      setOpen(true);
                    }}
                  >
                    <Pencil className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="destructive"
                    onClick={() => requestDelete(event)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))
        )}
      </div>

      {/* Modal Crear / Editar */}
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="text-2xl">
              {editing ? "Editar Evento" : "Crear Nuevo Evento"}
            </DialogTitle>
          </DialogHeader>

          <div className="grid gap-6 py-4">
            <div className="grid gap-3">
              <Label htmlFor="nombre">Nombre del Evento *</Label>
              <Input
                id="nombre"
                value={form.nombre}
                onChange={(e) => setForm({ ...form, nombre: e.target.value })}
                placeholder="Ej: Fiesta Electrónica"
              />
            </div>

            <div className="grid gap-3">
              <Label htmlFor="detalle">Detalle o descripción</Label>
              <Textarea
                id="detalle"
                value={form.detalle}
                onChange={(e) => setForm({ ...form, detalle: e.target.value })}
                placeholder="Agregá información relevante..."
                className="min-h-[100px]"
              />
            </div>

            {!editing && (
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-3">
                  <Label htmlFor="fecha">Fecha *</Label>
                  <Input
                    id="fecha"
                    type="date"
                    value={form.fecha}
                    onChange={(e) =>
                      setForm({ ...form, fecha: e.target.value })
                    }
                  />
                </div>
                <div className="grid gap-3">
                  <Label htmlFor="capacidad">Capacidad *</Label>
                  <Input
                    id="capacidad"
                    type="number"
                    value={form.capacidad}
                    onChange={(e) =>
                      setForm({ ...form, capacidad: e.target.value })
                    }
                    placeholder="Ej: 1000"
                  />
                </div>
              </div>
            )}

            {editing && (
              <div className="grid gap-3">
                <Label htmlFor="capacidad">Capacidad *</Label>
                <Input
                  id="capacidad"
                  type="number"
                  value={form.capacidad}
                  onChange={(e) =>
                    setForm({ ...form, capacidad: e.target.value })
                  }
                />
              </div>
            )}
          </div>

          <div className="flex gap-3 justify-end">
            <Button variant="outline" onClick={() => setOpen(false)}>
              Cancelar
            </Button>
            <Button onClick={handleSave}>
              {editing ? "Guardar Cambios" : "Crear Evento"}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
      <Dialog
        open={deleteOpen}
        onOpenChange={(value) => {
          if (!value && deleteLoading) {
            return;
          }
          setDeleteOpen(value);
          if (!value) {
            setDeleteTarget(null);
          }
        }}
      >
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Eliminar evento</DialogTitle>
            <DialogDescription>
              {deleteTarget
                ? `¿Querés eliminar "${deleteTarget.nombre}"?`
                : "Esta acción no se puede deshacer."}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button
              variant="outline"
              onClick={() => {
                if (deleteLoading) return;
                setDeleteOpen(false);
                setDeleteTarget(null);
              }}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={handleDelete}
              disabled={deleteLoading}
              className="gap-2"
            >
              {deleteLoading && <Loader2 className="h-4 w-4 animate-spin" />}
              Eliminar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
