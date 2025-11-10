import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Calendar, Clock, MapPin, Users, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "@/hooks/use-toast";

interface Event {
  id: string;
  title: string;
  location: "santas" | "outdoor" | "kiddo";
  date: string;
  time: string;
  capacity: number;
  attendees: number;
  type: string;
}

export default function Eventos() {
  const [events, setEvents] = useState<Event[]>([
    {
      id: "1",
      title: "Noche de DJ Internacional",
      location: "santas",
      date: "2025-11-15",
      time: "23:00",
      capacity: 300,
      attendees: 245,
      type: "DJ Night",
    },
    {
      id: "2",
      title: "Pool Party Sunset",
      location: "outdoor",
      date: "2025-11-16",
      time: "18:00",
      capacity: 200,
      attendees: 150,
      type: "Pool Party",
    },
    {
      id: "3",
      title: "Cumpleaños Temático Super Héroes",
      location: "kiddo",
      date: "2025-11-17",
      time: "15:00",
      capacity: 50,
      attendees: 35,
      type: "Cumpleaños",
    },
    {
      id: "4",
      title: "Ladies Night",
      location: "santas",
      date: "2025-11-18",
      time: "22:00",
      capacity: 300,
      attendees: 180,
      type: "Fiesta Temática",
    },
    {
      id: "5",
      title: "After Office",
      location: "outdoor",
      date: "2025-11-19",
      time: "19:00",
      capacity: 200,
      attendees: 95,
      type: "After Office",
    },
  ]);
  const [open, setOpen] = useState(false);
  const [newEvent, setNewEvent] = useState({
    title: "",
    location: "santas" as Event["location"],
    date: "",
    time: "",
    capacity: "",
    type: "",
  });

  const handleAddEvent = () => {
    if (!newEvent.title || !newEvent.date || !newEvent.time || !newEvent.capacity) {
      toast({
        title: "Error",
        description: "Por favor completa todos los campos obligatorios",
        variant: "destructive",
      });
      return;
    }

    const event: Event = {
      id: String(events.length + 1),
      title: newEvent.title,
      location: newEvent.location,
      date: newEvent.date,
      time: newEvent.time,
      capacity: parseInt(newEvent.capacity),
      attendees: 0,
      type: newEvent.type || "General",
    };

    setEvents([...events, event]);
    setOpen(false);
    setNewEvent({
      title: "",
      location: "santas",
      date: "",
      time: "",
      capacity: "",
      type: "",
    });
    
    toast({
      title: "Evento creado",
      description: "El evento se agregó correctamente",
    });
  };

  const getLocationColor = (location: Event["location"]) => {
    switch (location) {
      case "santas":
        return "primary";
      case "outdoor":
        return "accent";
      case "kiddo":
        return "success";
    }
  };

  const getLocationLabel = (location: Event["location"]) => {
    switch (location) {
      case "santas":
        return "Santas Indoor";
      case "outdoor":
        return "Outdoor";
      case "kiddo":
        return "Kiddo";
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString("es-AR", { weekday: "long", day: "numeric", month: "long" });
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">Calendario de Eventos</h1>
          <p className="text-muted-foreground">Próximos eventos programados</p>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild>
            <Button size="lg" className="gap-2">
              <Plus className="h-5 w-5" />
              <span className="hidden lg:inline">Nuevo Evento</span>
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle className="text-2xl">Crear Nuevo Evento</DialogTitle>
            </DialogHeader>
            <div className="grid gap-6 py-4">
              <div className="grid gap-3">
                <Label htmlFor="title" className="text-base">Nombre del Evento *</Label>
                <Input
                  id="title"
                  value={newEvent.title}
                  onChange={(e) => setNewEvent({ ...newEvent, title: e.target.value })}
                  placeholder="Ej: Noche de DJ Internacional"
                  className="h-12 text-base"
                />
              </div>
              <div className="grid gap-3">
                <Label htmlFor="location" className="text-base">Sucursal *</Label>
                <Select
                  value={newEvent.location}
                  onValueChange={(value: Event["location"]) => setNewEvent({ ...newEvent, location: value })}
                >
                  <SelectTrigger className="h-12 text-base">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="santas">Santas Indoor</SelectItem>
                    <SelectItem value="outdoor">Outdoor</SelectItem>
                    <SelectItem value="kiddo">Kiddo</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-3">
                  <Label htmlFor="date" className="text-base">Fecha *</Label>
                  <Input
                    id="date"
                    type="date"
                    value={newEvent.date}
                    onChange={(e) => setNewEvent({ ...newEvent, date: e.target.value })}
                    className="h-12 text-base"
                  />
                </div>
                <div className="grid gap-3">
                  <Label htmlFor="time" className="text-base">Hora *</Label>
                  <Input
                    id="time"
                    type="time"
                    value={newEvent.time}
                    onChange={(e) => setNewEvent({ ...newEvent, time: e.target.value })}
                    className="h-12 text-base"
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-3">
                  <Label htmlFor="capacity" className="text-base">Capacidad *</Label>
                  <Input
                    id="capacity"
                    type="number"
                    value={newEvent.capacity}
                    onChange={(e) => setNewEvent({ ...newEvent, capacity: e.target.value })}
                    placeholder="Ej: 300"
                    className="h-12 text-base"
                  />
                </div>
                <div className="grid gap-3">
                  <Label htmlFor="type" className="text-base">Tipo de Evento</Label>
                  <Input
                    id="type"
                    value={newEvent.type}
                    onChange={(e) => setNewEvent({ ...newEvent, type: e.target.value })}
                    placeholder="Ej: DJ Night"
                    className="h-12 text-base"
                  />
                </div>
              </div>
            </div>
            <div className="flex gap-3 justify-end">
              <Button variant="outline" size="lg" onClick={() => setOpen(false)}>
                Cancelar
              </Button>
              <Button size="lg" onClick={handleAddEvent}>
                Crear Evento
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card className="border-border">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">Total Eventos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-foreground">{events.length}</div>
          </CardContent>
        </Card>
        <Card className="border-primary/50 bg-primary/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">Esta Semana</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-primary">5</div>
          </CardContent>
        </Card>
        <Card className="border-accent/50 bg-accent/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">Asistentes Esperados</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-accent">705</div>
          </CardContent>
        </Card>
      </div>

      <div className="space-y-4">
        {events.map((event) => {
          const occupancy = Math.round((event.attendees / event.capacity) * 100);
          const locationColor = getLocationColor(event.location);

          return (
            <Card
              key={event.id}
              className={cn(
                "border transition-all duration-200 hover:shadow-lg",
                `border-${locationColor}/30 hover:border-${locationColor}/60`
              )}
            >
              <CardContent className="p-6">
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                  <div className="flex-1 space-y-3">
                    <div className="flex items-start gap-3 flex-wrap">
                      <h3 className="text-2xl font-bold text-foreground">{event.title}</h3>
                      <Badge variant="outline" className={cn("text-sm", `border-${locationColor}`)}>
                        {event.type}
                      </Badge>
                    </div>
                    <div className="grid gap-2 md:grid-cols-2">
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <MapPin className={cn("h-4 w-4", `text-${locationColor}`)} />
                        <span className="text-sm">{getLocationLabel(event.location)}</span>
                      </div>
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <Calendar className="h-4 w-4 text-primary" />
                        <span className="text-sm capitalize">{formatDate(event.date)}</span>
                      </div>
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <Clock className="h-4 w-4 text-accent" />
                        <span className="text-sm">{event.time} hs</span>
                      </div>
                      <div className="flex items-center gap-2 text-muted-foreground">
                        <Users className="h-4 w-4 text-success" />
                        <span className="text-sm">
                          {event.attendees} / {event.capacity} personas
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="lg:text-right space-y-2">
                    <div>
                      <p className="text-sm text-muted-foreground mb-1">Ocupación</p>
                      <p className={cn("text-4xl font-bold", `text-${locationColor}`)}>{occupancy}%</p>
                    </div>
                    <div className="w-full lg:w-32 h-2 bg-muted rounded-full overflow-hidden">
                      <div
                        className={cn("h-full transition-all duration-500", `bg-${locationColor}`)}
                        style={{ width: `${occupancy}%` }}
                      />
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
