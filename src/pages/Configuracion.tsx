import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Settings, Plus, Trash2, Edit } from "lucide-react";
import { toast } from "@/hooks/use-toast";

interface TicketType {
  id: string;
  name: string;
  price: number;
  location: string;
}

export default function Configuracion() {
  const [ticketTypes, setTicketTypes] = useState<TicketType[]>([
    { id: "1", name: "Entrada General", price: 5000, location: "Santas" },
    { id: "2", name: "Entrada VIP", price: 8000, location: "Santas" },
    { id: "3", name: "Entrada Pool Party", price: 6000, location: "Outdoor" },
    { id: "4", name: "Cumpleaños Estándar", price: 15000, location: "Kiddo" },
    { id: "5", name: "Cumpleaños Premium", price: 25000, location: "Kiddo" },
  ]);

  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [formData, setFormData] = useState({ name: "", price: "", location: "" });

  const handleSave = () => {
    if (!formData.name || !formData.price || !formData.location) {
      toast({
        title: "Error",
        description: "Por favor completa todos los campos",
        variant: "destructive",
      });
      return;
    }

    if (editingId) {
      setTicketTypes(
        ticketTypes.map((t) =>
          t.id === editingId
            ? { ...t, name: formData.name, price: parseFloat(formData.price), location: formData.location }
            : t
        )
      );
      toast({ title: "Actualizado", description: "Tipo de entrada modificado correctamente" });
    } else {
      const newTicket: TicketType = {
        id: String(ticketTypes.length + 1),
        name: formData.name,
        price: parseFloat(formData.price),
        location: formData.location,
      };
      setTicketTypes([...ticketTypes, newTicket]);
      toast({ title: "Creado", description: "Nuevo tipo de entrada agregado" });
    }

    setOpen(false);
    setEditingId(null);
    setFormData({ name: "", price: "", location: "" });
  };

  const handleEdit = (ticket: TicketType) => {
    setEditingId(ticket.id);
    setFormData({ name: ticket.name, price: String(ticket.price), location: ticket.location });
    setOpen(true);
  };

  const handleDelete = (id: string) => {
    setTicketTypes(ticketTypes.filter((t) => t.id !== id));
    toast({ title: "Eliminado", description: "Tipo de entrada eliminado" });
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">Configuración</h1>
          <p className="text-muted-foreground">Precios y tipos de entrada</p>
        </div>
        <Dialog
          open={open}
          onOpenChange={(isOpen) => {
            setOpen(isOpen);
            if (!isOpen) {
              setEditingId(null);
              setFormData({ name: "", price: "", location: "" });
            }
          }}
        >
          <DialogTrigger asChild>
            <Button size="lg" className="gap-2">
              <Plus className="h-5 w-5" />
              <span className="hidden lg:inline">Nuevo Tipo</span>
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="text-2xl">
                {editingId ? "Editar Tipo de Entrada" : "Nuevo Tipo de Entrada"}
              </DialogTitle>
            </DialogHeader>
            <div className="grid gap-6 py-4">
              <div className="grid gap-3">
                <Label htmlFor="name" className="text-base">
                  Nombre
                </Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="Ej: Entrada General"
                  className="h-12 text-base"
                />
              </div>
              <div className="grid gap-3">
                <Label htmlFor="price" className="text-base">
                  Precio ($)
                </Label>
                <Input
                  id="price"
                  type="number"
                  value={formData.price}
                  onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                  placeholder="Ej: 5000"
                  className="h-12 text-base"
                />
              </div>
              <div className="grid gap-3">
                <Label htmlFor="location" className="text-base">
                  Sucursal
                </Label>
                <Input
                  id="location"
                  value={formData.location}
                  onChange={(e) => setFormData({ ...formData, location: e.target.value })}
                  placeholder="Ej: Santas, Outdoor, Kiddo"
                  className="h-12 text-base"
                />
              </div>
            </div>
            <div className="flex gap-3 justify-end">
              <Button
                variant="outline"
                size="lg"
                onClick={() => {
                  setOpen(false);
                  setEditingId(null);
                  setFormData({ name: "", price: "", location: "" });
                }}
              >
                Cancelar
              </Button>
              <Button size="lg" onClick={handleSave}>
                {editingId ? "Guardar Cambios" : "Crear"}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {ticketTypes.map((ticket) => (
          <Card key={ticket.id} className="border-border hover:border-primary/50 transition-all">
            <CardHeader className="pb-3">
              <CardTitle className="text-lg flex items-center justify-between">
                <span className="text-foreground">{ticket.name}</span>
                <div className="flex gap-2">
                  <Button
                    size="icon"
                    variant="ghost"
                    onClick={() => handleEdit(ticket)}
                    className="h-8 w-8 text-accent hover:text-accent hover:bg-accent/10"
                  >
                    <Edit className="h-4 w-4" />
                  </Button>
                  <Button
                    size="icon"
                    variant="ghost"
                    onClick={() => handleDelete(ticket.id)}
                    className="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Precio</span>
                  <span className="text-2xl font-bold text-primary">${ticket.price}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Sucursal</span>
                  <span className="text-sm font-semibold text-accent">{ticket.location}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
