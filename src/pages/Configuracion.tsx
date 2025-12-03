import { useState, useEffect } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Plus, Trash2, Edit, Loader2 } from "lucide-react";
import { Switch } from "@/components/ui/switch";
import { toast } from "@/hooks/use-toast";
import { api } from "@/services/api";

interface Entrada {
  id: number;
  nombre: string;
  precio_base: number;
  cambio_automatico?: boolean;
  hora_inicio_cambio?: string | null;
  hora_fin_cambio?: string | null;
  nuevo_precio?: number | null;
  activo?: boolean;
}

const normalizeEntradaName = (name: string) =>
  name
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();

const PROTECTED_ENTRADAS = ["gratis", "clasica", "anticipada"];

const isProtectedEntrada = (entrada: Entrada | undefined) => {
  if (!entrada) return false;
  return PROTECTED_ENTRADAS.includes(normalizeEntradaName(entrada.nombre));
};

export default function Configuracion() {
  const [entradas, setEntradas] = useState<Entrada[]>([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);

  const [formData, setFormData] = useState({
    nombre: "",
    precio_base: "",
    cambio_automatico: false,
    hora_inicio_cambio: "",
    hora_fin_cambio: "",
    nuevo_precio: "",
  });

  const fetchEntradas = async () => {
    setLoading(true);
    try {
      const { data } = await api.get("/entradas.php");
      if (Array.isArray(data)) setEntradas(data);
      else throw new Error("Respuesta inválida del servidor");
    } catch (error) {
      console.error(error);
      toast({
        title: "Error",
        description:
          "No se pudieron cargar las entradas desde la base de datos.",
        variant: "destructive",
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEntradas();
  }, []);

  // Actualización automática del precio según hora actual
  useEffect(() => {
    const interval = setInterval(() => {
      const now = new Date();
      const horaActual = now.toTimeString().slice(0, 5);
      setEntradas((prev) =>
        prev.map((e) => {
          if (
            e.cambio_automatico &&
            e.hora_inicio_cambio &&
            e.hora_fin_cambio &&
            e.nuevo_precio
          ) {
            if (
              horaActual >= e.hora_inicio_cambio &&
              horaActual <= e.hora_fin_cambio
            ) {
              return { ...e, precio_base: e.nuevo_precio };
            }
          }
          return e;
        })
      );
    }, 60000);
    return () => clearInterval(interval);
  }, []);

  const handleSave = async () => {
    if (!formData.nombre || !formData.precio_base) {
      toast({
        title: "Error",
        description: "El nombre y el precio son obligatorios.",
        variant: "destructive",
      });
      return;
    }

    if (formData.cambio_automatico) {
      if (!formData.hora_inicio_cambio || !formData.hora_fin_cambio) {
        toast({
          title: "Error",
          description:
            "Debes seleccionar ambas horas de inicio y fin del cambio de precio.",
          variant: "destructive",
        });
        return;
      }
      if (!formData.nuevo_precio) {
        toast({
          title: "Error",
          description:
            "Debes indicar el nuevo precio si hay cambio automático.",
          variant: "destructive",
        });
        return;
      }
    }

    try {
      const payload = {
        nombre: formData.nombre,
        precio_base: parseFloat(formData.precio_base),
        cambio_automatico: formData.cambio_automatico ? 1 : 0,
        hora_inicio_cambio: formData.cambio_automatico
          ? formData.hora_inicio_cambio
          : null,
        hora_fin_cambio: formData.cambio_automatico
          ? formData.hora_fin_cambio
          : null,
        nuevo_precio: formData.cambio_automatico
          ? parseFloat(formData.nuevo_precio || "0")
          : null,
      };

      if (editingId) {
        await api.put(`/entradas.php?id=${editingId}`, payload);
        toast({
          title: "Actualizado",
          description: "Entrada modificada correctamente.",
        });
      } else {
        await api.post("/entradas.php", payload);
        toast({ title: "Creado", description: "Nueva entrada agregada." });
      }

      setOpen(false);
      setEditingId(null);
      setFormData({
        nombre: "",
        precio_base: "",
        cambio_automatico: false,
        hora_inicio_cambio: "",
        hora_fin_cambio: "",
        nuevo_precio: "",
      });
      fetchEntradas();
    } catch (error) {
      console.error("Error al guardar entrada:", error);
      toast({
        title: "Error",
        description: "No se pudo guardar la entrada.",
        variant: "destructive",
      });
    }
  };

  const handleEdit = (entrada: Entrada) => {
    setEditingId(entrada.id);
    setFormData({
      nombre: entrada.nombre,
      precio_base: String(entrada.precio_base),
      cambio_automatico: Boolean(entrada.cambio_automatico),
      hora_inicio_cambio: entrada.hora_inicio_cambio || "",
      hora_fin_cambio: entrada.hora_fin_cambio || "",
      nuevo_precio: entrada.nuevo_precio ? String(entrada.nuevo_precio) : "",
    });
    setOpen(true);
  };

  const handleDelete = async (id: number) => {
    const entrada = entradas.find((item) => item.id === id);
    if (isProtectedEntrada(entrada)) {
      toast({
        title: "Acción no permitida",
        description:
          "Las entradas gratis, clásica y anticipada no pueden eliminarse.",
        variant: "destructive",
      });
      return;
    }
    if (!window.confirm("¿Seguro que deseas eliminar esta entrada?")) return;
    try {
      await api.delete(`/entradas.php?id=${id}`);
      toast({
        title: "Eliminado",
        description: "Tipo de entrada eliminado correctamente.",
      });
      fetchEntradas();
    } catch {
      toast({
        title: "Error",
        description: "No se pudo eliminar la entrada.",
        variant: "destructive",
      });
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">
            Configuración
          </h1>
          <p className="text-muted-foreground">Precios y tipos de entrada</p>
        </div>

        <Dialog
          open={open}
          onOpenChange={(isOpen) => {
            setOpen(isOpen);
            if (!isOpen) {
              setEditingId(null);
              setFormData({
                nombre: "",
                precio_base: "",
                cambio_automatico: false,
                hora_inicio_cambio: "",
                hora_fin_cambio: "",
                nuevo_precio: "",
              });
            }
          }}
        >
          <DialogTrigger asChild>
            <Button size="lg" className="gap-2">
              <Plus className="h-5 w-5" />
              <span className="hidden lg:inline">Nueva Entrada</span>
            </Button>
          </DialogTrigger>

          <DialogContent className="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle className="text-2xl">
                {editingId ? "Editar Entrada" : "Nueva Entrada"}
              </DialogTitle>
            </DialogHeader>

            <div className="grid gap-6 py-4">
              <div className="grid gap-3">
                <Label htmlFor="nombre" className="text-base">
                  Nombre
                </Label>
                <Input
                  id="nombre"
                  value={formData.nombre}
                  onChange={(e) =>
                    setFormData({ ...formData, nombre: e.target.value })
                  }
                  placeholder="Ej: Entrada General"
                  className="h-12 text-base"
                />
              </div>

              <div className="grid gap-3">
                <Label htmlFor="precio" className="text-base">
                  Precio base ($)
                </Label>
                <Input
                  id="precio"
                  type="number"
                  value={formData.precio_base}
                  onChange={(e) =>
                    setFormData({ ...formData, precio_base: e.target.value })
                  }
                  placeholder="Ej: 5000"
                  className="h-12 text-base"
                />
              </div>

              <div className="flex items-center justify-between border border-border rounded-lg px-4 py-3">
                <div>
                  <p className="font-medium">
                    ¿Hay dos precios durante la noche?
                  </p>
                  <p className="text-sm text-muted-foreground">
                    Permite establecer un precio distinto por horario.
                  </p>
                </div>
                <Switch
                  checked={formData.cambio_automatico}
                  onCheckedChange={(checked) =>
                    setFormData({ ...formData, cambio_automatico: checked })
                  }
                />
              </div>

              {formData.cambio_automatico && (
                <>
                  <div className="grid gap-3">
                    <Label htmlFor="hora_inicio_cambio" className="text-base">
                      Hora de inicio del nuevo precio
                    </Label>
                    <Input
                      id="hora_inicio_cambio"
                      type="time"
                      value={formData.hora_inicio_cambio}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          hora_inicio_cambio: e.target.value,
                        })
                      }
                      className="h-12 text-base text-white bg-transparent [color-scheme:dark]"
                    />
                  </div>

                  <div className="grid gap-3">
                    <Label htmlFor="hora_fin_cambio" className="text-base">
                      Hora de finalización del nuevo precio
                    </Label>
                    <Input
                      id="hora_fin_cambio"
                      type="time"
                      value={formData.hora_fin_cambio}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          hora_fin_cambio: e.target.value,
                        })
                      }
                      className="h-12 text-base text-white bg-transparent [color-scheme:dark]"
                    />
                  </div>

                  <div className="grid gap-3">
                    <Label htmlFor="nuevo_precio" className="text-base">
                      Nuevo precio ($)
                    </Label>
                    <Input
                      id="nuevo_precio"
                      type="number"
                      value={formData.nuevo_precio}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          nuevo_precio: e.target.value,
                        })
                      }
                      placeholder="Ej: 8000"
                      className="h-12 text-base"
                    />
                  </div>
                </>
              )}
            </div>

            <div className="flex gap-3 justify-end pb-4">
              <Button
                variant="outline"
                size="lg"
                onClick={() => {
                  setOpen(false);
                  setEditingId(null);
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

      {loading ? (
        <div className="flex justify-center py-20 text-muted-foreground">
          <Loader2 className="h-5 w-5 animate-spin mr-2" /> Cargando entradas...
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {entradas.map((entrada) => (
            <Card
              key={entrada.id}
              className="border-border hover:border-primary/50 transition-all"
            >
              <CardHeader className="pb-3">
                <CardTitle className="text-lg flex items-center justify-between">
                  <span>{entrada.nombre}</span>
                  <div className="flex gap-2">
                    <Button
                      size="icon"
                      variant="ghost"
                      onClick={() => handleEdit(entrada)}
                      className="h-8 w-8 text-accent hover:bg-accent/10"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      size="icon"
                      variant="ghost"
                      onClick={() => handleDelete(entrada.id)}
                      disabled={isProtectedEntrada(entrada)}
                      className="h-8 w-8 text-destructive hover:bg-destructive/10"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </CardTitle>
              </CardHeader>

              <CardContent>
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">
                      Precio actual
                    </span>
                    <span className="text-2xl font-bold text-primary">
                      ${entrada.precio_base}
                    </span>
                  </div>

                  {entrada.cambio_automatico ? (
                    <>
                      <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">
                          Nuevo precio
                        </span>
                        <span className="text-lg font-semibold text-accent">
                          ${entrada.nuevo_precio}
                        </span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">
                          Vigente de
                        </span>
                        <span className="text-sm font-medium">
                          {entrada.hora_inicio_cambio} -{" "}
                          {entrada.hora_fin_cambio}
                        </span>
                      </div>
                    </>
                  ) : (
                    <p className="text-sm text-muted-foreground italic">
                      Sin cambio de precio.
                    </p>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
