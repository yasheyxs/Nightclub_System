import { useState } from "react";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from "@/components/ui/card";
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
import {
  ShoppingCart,
  Plus,
  DollarSign,
  TrendingUp,
  Minus,
} from "lucide-react";
import { toast } from "@/hooks/use-toast";

interface Sale {
  id: string;
  product: string;
  quantity: number;
  price: number;
  total: number;
  bar: string;
  timestamp: string;
}

export default function Ventas() {
  // ================== Datos del historial de ventas ==================
  const [sales, setSales] = useState<Sale[]>([
    {
      id: "1",
      product: "Fernet Branca",
      quantity: 2,
      price: 4500,
      total: 9000,
      bar: "Barra Principal",
      timestamp: new Date().toLocaleString("es-AR"),
    },
    {
      id: "2",
      product: "Cerveza Corona",
      quantity: 5,
      price: 3500,
      total: 17500,
      bar: "Barra Exterior",
      timestamp: new Date().toLocaleString("es-AR"),
    },
  ]);

  const [open, setOpen] = useState(false);
  const [newSale, setNewSale] = useState({
    product: "",
    quantity: "",
    price: "",
    bar: "",
  });

  const handleAddSale = () => {
    if (
      !newSale.product ||
      !newSale.quantity ||
      !newSale.price ||
      !newSale.bar
    ) {
      toast({
        title: "Error",
        description: "Por favor completa todos los campos",
        variant: "destructive",
      });
      return;
    }

    const sale: Sale = {
      id: String(sales.length + 1),
      product: newSale.product,
      quantity: parseInt(newSale.quantity),
      price: parseFloat(newSale.price),
      total: parseInt(newSale.quantity) * parseFloat(newSale.price),
      bar: newSale.bar,
      timestamp: new Date().toLocaleString("es-AR"),
    };

    setSales([sale, ...sales]);
    setOpen(false);
    setNewSale({ product: "", quantity: "", price: "", bar: "" });

    toast({
      title: "Venta registrada",
      description: "La venta se agregó correctamente",
    });
  };

  const totalSales = sales.reduce((sum, sale) => sum + sale.total, 0);
  const totalProducts = sales.reduce((sum, sale) => sum + sale.quantity, 0);

  // ================== Configuración del panel de categorías ==================
  const productCategories = [
    { value: "tragos", label: "Tragos" },
    { value: "botellas", label: "Botellas" },
    { value: "otros", label: "Otros" },
  ];

  const saleConfigurations: Record<
    string,
    { key: string; label: string; description: string }[]
  > = {
    tragos: [
      {
        key: "clasico",
        label: "Trago individual",
        description: "Consumición estándar por vaso.",
      },
      {
        key: "premium",
        label: "Trago premium",
        description: "Bebidas especiales o coctelería de autor.",
      },
      {
        key: "shot",
        label: "Shots",
        description: "Ventas rápidas para grupos o promociones.",
      },
    ],
    botellas: [
      {
        key: "estandar",
        label: "Botella estándar",
        description: "Presentación base para mesas.",
      },
      {
        key: "reserva",
        label: "Botella reserva",
        description: "Selecciones premium o ediciones especiales.",
      },
    ],
    otros: [
      {
        key: "sin-alcohol",
        label: "Sin alcohol",
        description: "Bebidas preparadas o gaseosas.",
      },
      {
        key: "snacks",
        label: "Snacks",
        description: "Complementos y acompañamientos.",
      },
    ],
  };

  const createInitialSalesCounters = () => {
    const counters: Record<string, Record<string, number>> = {};
    for (const category of productCategories) {
      counters[category.value] =
        saleConfigurations[category.value]?.reduce(
          (acc, item) => ({ ...acc, [item.key]: 0 }),
          {}
        ) ?? {};
    }
    return counters;
  };

  const [selectedCategory, setSelectedCategory] = useState(
    productCategories[0].value
  );
  const [salesCounters, setSalesCounters] = useState(
    createInitialSalesCounters
  );

  const adjustSalesCounter = (
    category: string,
    key: string,
    change: number
  ) => {
    setSalesCounters((previous) => ({
      ...previous,
      [category]: {
        ...previous[category],
        [key]: Math.max(0, (previous[category]?.[key] ?? 0) + change),
      },
    }));
  };

  const currentSaleOptions = saleConfigurations[selectedCategory] ?? [];
  const currentSalesCounters = salesCounters[selectedCategory] ?? {};

  // ================== Render ==================
  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">
            Ventas en Barras
          </h1>
          <p className="text-muted-foreground">
            Registro de ventas en tiempo real
          </p>
        </div>

        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild>
            <Button size="lg" className="gap-2">
              <Plus className="h-5 w-5" />
              <span className="hidden lg:inline">Registrar Venta</span>
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="text-2xl">
                Registrar Nueva Venta
              </DialogTitle>
            </DialogHeader>
            <div className="grid gap-6 py-4">
              <div className="grid gap-3">
                <Label htmlFor="product" className="text-base">
                  Producto
                </Label>
                <Input
                  id="product"
                  value={newSale.product}
                  onChange={(e) =>
                    setNewSale({ ...newSale, product: e.target.value })
                  }
                  placeholder="Ej: Fernet Branca"
                  className="h-12 text-base"
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-3">
                  <Label htmlFor="quantity" className="text-base">
                    Cantidad
                  </Label>
                  <Input
                    id="quantity"
                    type="number"
                    value={newSale.quantity}
                    onChange={(e) =>
                      setNewSale({ ...newSale, quantity: e.target.value })
                    }
                    placeholder="Ej: 2"
                    className="h-12 text-base"
                  />
                </div>
                <div className="grid gap-3">
                  <Label htmlFor="price" className="text-base">
                    Precio Unitario ($)
                  </Label>
                  <Input
                    id="price"
                    type="number"
                    value={newSale.price}
                    onChange={(e) =>
                      setNewSale({ ...newSale, price: e.target.value })
                    }
                    placeholder="Ej: 4500"
                    className="h-12 text-base"
                  />
                </div>
              </div>
              <div className="grid gap-3">
                <Label htmlFor="bar" className="text-base">
                  Barra
                </Label>
                <Select
                  value={newSale.bar}
                  onValueChange={(value) =>
                    setNewSale({ ...newSale, bar: value })
                  }
                >
                  <SelectTrigger className="h-12 text-base">
                    <SelectValue placeholder="Seleccionar barra" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Barra Principal">
                      Barra Principal
                    </SelectItem>
                    <SelectItem value="Barra Exterior">
                      Barra Exterior
                    </SelectItem>
                    <SelectItem value="Barra VIP">Barra VIP</SelectItem>
                    <SelectItem value="Barra Kiddo">Barra Kiddo</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="flex gap-3 justify-end">
              <Button
                variant="outline"
                size="lg"
                onClick={() => setOpen(false)}
              >
                Cancelar
              </Button>
              <Button size="lg" onClick={handleAddSale}>
                Registrar
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      {/* ========== Totales ========== */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card className="border-primary/50 bg-primary/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <DollarSign className="h-4 w-4 text-primary" />
              Total Recaudado
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-primary">
              ${totalSales.toLocaleString()}
            </div>
          </CardContent>
        </Card>

        <Card className="border-accent/50 bg-accent/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <ShoppingCart className="h-4 w-4 text-accent" />
              Productos Vendidos
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-accent">
              {totalProducts}
            </div>
          </CardContent>
        </Card>

        <Card className="border-success/50 bg-success/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground flex items-center gap-2">
              <TrendingUp className="h-4 w-4 text-success" />
              Ventas Registradas
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-success">
              {sales.length}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* ========== Sección de registro de productos (tragos, botellas, etc.) ========== */}
      <section className="space-y-4">
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 className="text-2xl font-semibold text-foreground">
              Registro de ventas
            </h2>
            <p className="text-muted-foreground">
              Selecciona la categoría de producto y utiliza los controles
              rápidos para llevar la cuenta.
            </p>
          </div>
          <div className="w-full md:w-60">
            <Label
              htmlFor="product-category"
              className="mb-2 block text-sm font-medium text-muted-foreground"
            >
              Filtrar por categoría
            </Label>
            <Select
              value={selectedCategory}
              onValueChange={setSelectedCategory}
            >
              <SelectTrigger id="product-category" className="h-12 text-base">
                <SelectValue placeholder="Elegir categoría" />
              </SelectTrigger>
              <SelectContent>
                {productCategories.map((category) => (
                  <SelectItem key={category.value} value={category.value}>
                    {category.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="grid gap-4 lg:grid-cols-3">
          {currentSaleOptions.map((product) => (
            <Card key={product.key} className="border border-border">
              <CardHeader className="space-y-1">
                <CardTitle className="text-lg font-semibold text-foreground">
                  {product.label}
                </CardTitle>
                <CardDescription>{product.description}</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-baseline justify-between">
                  <span className="text-sm text-muted-foreground">Total</span>
                  <span className="text-4xl font-bold text-accent">
                    {currentSalesCounters[product.key] ?? 0}
                  </span>
                </div>
                <div className="flex gap-3">
                  <Button
                    variant="outline"
                    type="button"
                    size="lg"
                    className="h-14 w-full"
                    onClick={() =>
                      adjustSalesCounter(selectedCategory, product.key, -1)
                    }
                  >
                    <Minus className="h-5 w-5" />
                  </Button>
                  <Button
                    type="button"
                    size="lg"
                    className="h-14 w-full bg-gradient-primary"
                    onClick={() =>
                      adjustSalesCounter(selectedCategory, product.key, 1)
                    }
                  >
                    <Plus className="h-5 w-5" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        {currentSaleOptions.length === 0 && (
          <Card className="border border-dashed border-border">
            <CardContent className="py-10 text-center text-sm text-muted-foreground">
              Configura los productos de esta categoría para comenzar a
              registrar ventas.
            </CardContent>
          </Card>
        )}
      </section>

      {/* ========== Historial de ventas ========== */}
      <Card>
        <CardHeader>
          <CardTitle>Historial de Ventas</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="text-base">Producto</TableHead>
                <TableHead className="text-base">Cantidad</TableHead>
                <TableHead className="text-base">Precio Unit.</TableHead>
                <TableHead className="text-base">Total</TableHead>
                <TableHead className="text-base">Barra</TableHead>
                <TableHead className="text-base">Fecha/Hora</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {sales.map((sale) => (
                <TableRow key={sale.id}>
                  <TableCell className="font-medium text-base">
                    {sale.product}
                  </TableCell>
                  <TableCell className="text-base">{sale.quantity}</TableCell>
                  <TableCell className="text-base">
                    ${sale.price.toLocaleString()}
                  </TableCell>
                  <TableCell className="text-base font-bold text-primary">
                    ${sale.total.toLocaleString()}
                  </TableCell>
                  <TableCell className="text-base text-accent">
                    {sale.bar}
                  </TableCell>
                  <TableCell className="text-base text-muted-foreground">
                    {sale.timestamp}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
