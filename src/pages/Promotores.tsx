import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Trophy,
  TrendingUp,
  Ticket,
  DollarSign,
  Plus,
  Edit,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { toast } from "@/hooks/use-toast";

interface Promoter {
  id: string;
  name: string;
  ticketsSold: number;
  revenue: number;
  ranking: number;
  trend: "up" | "down" | "stable";
  code: string;
}

export default function Promotores() {
  const [promoters, setPromoters] = useState<Promoter[]>([
    {
      id: "1",
      name: "Juan Pérez",
      ticketsSold: 145,
      revenue: 72500,
      ranking: 1,
      trend: "up",
      code: "JP2024",
    },
    {
      id: "2",
      name: "María García",
      ticketsSold: 132,
      revenue: 66000,
      ranking: 2,
      trend: "up",
      code: "MG2024",
    },
    {
      id: "3",
      name: "Carlos Rodríguez",
      ticketsSold: 118,
      revenue: 59000,
      ranking: 3,
      trend: "stable",
      code: "CR2024",
    },
    {
      id: "4",
      name: "Ana Martínez",
      ticketsSold: 105,
      revenue: 52500,
      ranking: 4,
      trend: "down",
      code: "AM2024",
    },
    {
      id: "5",
      name: "Luis Fernández",
      ticketsSold: 98,
      revenue: 49000,
      ranking: 5,
      trend: "up",
      code: "LF2024",
    },
    {
      id: "6",
      name: "Sofia López",
      ticketsSold: 87,
      revenue: 43500,
      ranking: 6,
      trend: "stable",
      code: "SL2024",
    },
  ]);

  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [formData, setFormData] = useState({ name: "", code: "" });

  const handleSave = () => {
    if (!formData.name || !formData.code) {
      toast({
        title: "Error",
        description: "Por favor completa todos los campos",
        variant: "destructive",
      });
      return;
    }

    if (editingId) {
      setPromoters(
        promoters.map((p) =>
          p.id === editingId
            ? { ...p, name: formData.name, code: formData.code }
            : p
        )
      );
      toast({
        title: "Actualizado",
        description: "Promotor modificado correctamente",
      });
    } else {
      const newPromoter: Promoter = {
        id: String(promoters.length + 1),
        name: formData.name,
        ticketsSold: 0,
        revenue: 0,
        ranking: promoters.length + 1,
        trend: "stable",
        code: formData.code,
      };
      setPromoters([...promoters, newPromoter]);
      toast({ title: "Creado", description: "Nuevo promotor agregado" });
    }

    setOpen(false);
    setEditingId(null);
    setFormData({ name: "", code: "" });
  };

  const handleEdit = (promoter: Promoter) => {
    setEditingId(promoter.id);
    setFormData({ name: promoter.name, code: promoter.code });
    setOpen(true);
  };

  const handleDelete = (id: string) => {
    setPromoters(promoters.filter((p) => p.id !== id));
    toast({ title: "Eliminado", description: "Promotor eliminado" });
  };

  const getTrendIcon = (trend: Promoter["trend"]) => {
    if (trend === "up") return "↑";
    if (trend === "down") return "↓";
    return "→";
  };

  const getTrendColor = (trend: Promoter["trend"]) => {
    if (trend === "up") return "text-success";
    if (trend === "down") return "text-destructive";
    return "text-muted-foreground";
  };

  const getRankingBadge = (ranking: number) => {
    if (ranking === 1)
      return "bg-accent text-accent-foreground shadow-glow-accent";
    if (ranking === 2) return "bg-muted text-foreground";
    if (ranking === 3) return "bg-warning/20 text-warning";
    return "bg-card text-foreground";
  };

  const totalTickets = promoters.reduce((sum, p) => sum + p.ticketsSold, 0);
  const totalRevenue = promoters.reduce((sum, p) => sum + p.revenue, 0);

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">
            Promotores
          </h1>
          <p className="text-muted-foreground">
            Ranking y desempeño del equipo
          </p>
        </div>
        <Dialog
          open={open}
          onOpenChange={(isOpen) => {
            setOpen(isOpen);
            if (!isOpen) {
              setEditingId(null);
              setFormData({ name: "", code: "" });
            }
          }}
        >
          <DialogTrigger asChild>
            <Button size="lg" className="gap-2">
              <Plus className="h-5 w-5" />
              <span className="hidden lg:inline">Nuevo Promotor</span>
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="text-2xl">
                {editingId ? "Editar Promotor" : "Nuevo Promotor"}
              </DialogTitle>
            </DialogHeader>
            <div className="grid gap-6 py-4">
              <div className="grid gap-3">
                <Label htmlFor="name" className="text-base">
                  Nombre Completo *
                </Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) =>
                    setFormData({ ...formData, name: e.target.value })
                  }
                  placeholder="Ej: Juan Pérez"
                  className="h-12 text-base"
                />
              </div>
              <div className="grid gap-3">
                <Label htmlFor="code" className="text-base">
                  Código *
                </Label>
                <Input
                  id="code"
                  value={formData.code}
                  onChange={(e) =>
                    setFormData({ ...formData, code: e.target.value })
                  }
                  placeholder="Ej: JP2024"
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
                  setFormData({ name: "", code: "" });
                }}
              >
                Cancelar
              </Button>
              <Button size="lg" onClick={handleSave}>
                {editingId ? "Guardar Cambios" : "Crear Promotor"}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card className="border-border">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <Trophy className="h-4 w-4 text-accent" />
              Promotores Activos
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-foreground">
              {promoters.length}
            </div>
          </CardContent>
        </Card>
        <Card className="border-primary/50 bg-primary/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <Ticket className="h-4 w-4 text-primary" />
              Total Entradas Vendidas
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-primary">
              {totalTickets}
            </div>
          </CardContent>
        </Card>
        <Card className="border-success/50 bg-success/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <DollarSign className="h-4 w-4 text-success" />
              Recaudación Total
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-success">
              ${totalRevenue.toLocaleString("es-AR")}
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="space-y-3">
        {promoters.map((promoter) => (
          <Card
            key={promoter.id}
            className={cn(
              "transition-all duration-200 hover:shadow-lg",
              promoter.ranking <= 3 && "border-primary/30"
            )}
          >
            <CardContent className="p-6">
              <div className="flex flex-col lg:flex-row lg:items-center gap-4">
                <div
                  className={cn(
                    "flex items-center justify-center w-16 h-16 rounded-full text-2xl font-bold",
                    getRankingBadge(promoter.ranking)
                  )}
                >
                  {promoter.ranking === 1 && <Trophy className="h-8 w-8" />}
                  {promoter.ranking !== 1 && promoter.ranking}
                </div>

                <div className="flex-1 space-y-2">
                  <div className="flex items-center gap-3 flex-wrap">
                    <h3 className="text-2xl font-bold text-foreground">
                      {promoter.name}
                    </h3>
                    <Badge variant="outline" className="font-mono text-xs">
                      {promoter.code}
                    </Badge>
                    <div
                      className={cn(
                        "flex items-center gap-1 text-sm font-semibold",
                        getTrendColor(promoter.trend)
                      )}
                    >
                      <TrendingUp className="h-4 w-4" />
                      <span>{getTrendIcon(promoter.trend)}</span>
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <div>
                      <p className="text-xs text-muted-foreground uppercase tracking-wide">
                        Entradas
                      </p>
                      <p className="text-2xl font-bold text-primary">
                        {promoter.ticketsSold}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-muted-foreground uppercase tracking-wide">
                        Recaudación
                      </p>
                      <p className="text-2xl font-bold text-success">
                        ${(promoter.revenue / 1000).toFixed(0)}k
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-muted-foreground uppercase tracking-wide">
                        Promedio
                      </p>
                      <p className="text-2xl font-bold text-accent">
                        $
                        {promoter.ticketsSold > 0
                          ? Math.round(promoter.revenue / promoter.ticketsSold)
                          : 0}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-muted-foreground uppercase tracking-wide">
                        Participación
                      </p>
                      <p className="text-2xl font-bold text-foreground">
                        {totalTickets > 0
                          ? Math.round(
                              (promoter.ticketsSold / totalTickets) * 100
                            )
                          : 0}
                        %
                      </p>
                    </div>
                  </div>
                </div>

                <div className="flex gap-2 lg:flex-col">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleEdit(promoter)}
                    className="flex-1 lg:flex-initial gap-2"
                  >
                    <Edit className="h-4 w-4" />
                    <span className="lg:hidden">Editar</span>
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleDelete(promoter.id)}
                    className="flex-1 lg:flex-initial gap-2 text-destructive hover:text-destructive hover:bg-destructive/10"
                  >
                    <Trash2 className="h-4 w-4" />
                    <span className="lg:hidden">Eliminar</span>
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
