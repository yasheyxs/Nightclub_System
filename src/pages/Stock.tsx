import { FormEvent, useState } from "react";
import { LocationSelector, Location } from "@/components/LocationSelector";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  AlertCircle,
  CheckCircle,
  Minus,
  Plus,
  PlusCircle,
} from "lucide-react";
import { cn } from "@/lib/utils";

interface Product {
  id: string;
  name: string;
  stock: number;
  minStock: number;
  category: string;
}

const inventoryCategories = [
  { value: "bebidas", label: "Bebidas" },
  { value: "insumos", label: "Insumos" },
  { value: "otros", label: "Otros" },
];

export default function Stock() {
  const [selectedLocation, setSelectedLocation] = useState<Location>("santas");
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [newProduct, setNewProduct] = useState({
    name: "",
    category: "",
    quantity: "",
    minStock: "",
  });

  const [products, setProducts] = useState<Record<Location, Product[]>>({
    santas: [],
    outdoor: [],
    kiddo: [],
  });

  const updateStock = (productId: string, change: number) => {
    setProducts((previous) => ({
      ...previous,
      [selectedLocation]: previous[selectedLocation].map((product) =>
        product.id === productId
          ? { ...product, stock: Math.max(0, product.stock + change) }
          : product
      ),
    }));
  };

  const handleAddProduct = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!newProduct.name || !newProduct.category) {
      return;
    }

    const quantity = Number(newProduct.quantity) || 0;
    const minStock = Number(newProduct.minStock) || 0;

    setProducts((previous) => ({
      ...previous,
      [selectedLocation]: [
        ...previous[selectedLocation],
        {
          id: `${selectedLocation}-${Date.now()}`,
          name: newProduct.name,
          category:
            inventoryCategories.find(
              (item) => item.value === newProduct.category
            )?.label ?? newProduct.category,
          stock: quantity,
          minStock,
        },
      ],
    }));

    setNewProduct({ name: "", category: "", quantity: "", minStock: "" });
    setIsDialogOpen(false);
  };

  const getStockStatus = (product: Product) => {
    if (product.stock === 0) {
      return {
        label: "Agotado",
        color: "destructive",
        icon: AlertCircle,
      } as const;
    }

    if (product.stock < product.minStock) {
      return {
        label: "Stock bajo",
        color: "warning",
        icon: AlertCircle,
      } as const;
    }

    return {
      label: "Disponible",
      color: "success",
      icon: CheckCircle,
    } as const;
  };

  const currentProducts = products[selectedLocation];
  const lowStockCount = currentProducts.filter(
    (product) => product.stock < product.minStock
  ).length;
  const outOfStockCount = currentProducts.filter(
    (product) => product.stock === 0
  ).length;

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 className="text-4xl font-bold text-foreground mb-2">
            Gestión de Stock
          </h1>
          <p className="text-muted-foreground">
            Control de inventario en tiempo real
          </p>
        </div>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
          <LocationSelector
            selectedLocation={selectedLocation}
            onLocationChange={setSelectedLocation}
          />
          <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
            <DialogTrigger asChild>
              <Button size="lg" className="gap-2 bg-gradient-primary">
                <PlusCircle className="h-5 w-5" />
                Agregar producto
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Nuevo producto en inventario</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleAddProduct} className="space-y-5">
                <div className="grid gap-3">
                  <Label htmlFor="product-name">Nombre del producto</Label>
                  <Input
                    id="product-name"
                    value={newProduct.name}
                    onChange={(event) =>
                      setNewProduct((previous) => ({
                        ...previous,
                        name: event.target.value,
                      }))
                    }
                    placeholder="Ej: Botella 750ml"
                    className="h-12 text-base"
                  />
                </div>
                <div className="grid gap-3">
                  <Label htmlFor="product-category">Categoría</Label>
                  <Select
                    value={newProduct.category}
                    onValueChange={(value) =>
                      setNewProduct((previous) => ({
                        ...previous,
                        category: value,
                      }))
                    }
                  >
                    <SelectTrigger
                      id="product-category"
                      className="h-12 text-base"
                    >
                      <SelectValue placeholder="Seleccionar categoría" />
                    </SelectTrigger>
                    <SelectContent>
                      {inventoryCategories.length > 0 ? (
                        inventoryCategories.map((category) => (
                          <SelectItem
                            key={category.value}
                            value={category.value}
                          >
                            {category.label}
                          </SelectItem>
                        ))
                      ) : (
                        <div className="px-3 py-2 text-sm text-muted-foreground">
                          No hay categorías disponibles
                        </div>
                      )}
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="grid gap-2">
                    <Label htmlFor="product-quantity">Cantidad inicial</Label>
                    <Input
                      id="product-quantity"
                      type="number"
                      min={0}
                      value={newProduct.quantity}
                      onChange={(event) =>
                        setNewProduct((previous) => ({
                          ...previous,
                          quantity: event.target.value,
                        }))
                      }
                      placeholder="0"
                      className="h-12 text-base"
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="product-min-stock">Stock mínimo</Label>
                    <Input
                      id="product-min-stock"
                      type="number"
                      min={0}
                      value={newProduct.minStock}
                      onChange={(event) =>
                        setNewProduct((previous) => ({
                          ...previous,
                          minStock: event.target.value,
                        }))
                      }
                      placeholder="0"
                      className="h-12 text-base"
                    />
                  </div>
                </div>
                <DialogFooter className="gap-3 sm:flex-row sm:justify-end">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => setIsDialogOpen(false)}
                  >
                    Cancelar
                  </Button>
                  <Button type="submit" className="bg-gradient-primary">
                    Guardar producto
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card className="border-border">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">
              Total de productos
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-foreground">
              {currentProducts.length}
            </div>
          </CardContent>
        </Card>
        <Card className="border-warning/50 bg-warning/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">
              Stock bajo
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-warning">
              {lowStockCount}
            </div>
          </CardContent>
        </Card>
        <Card className="border-destructive/50 bg-destructive/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm text-muted-foreground">
              Agotados
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-destructive">
              {outOfStockCount}
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="space-y-3">
        {currentProducts.length === 0 && (
          <Card className="border border-dashed border-border">
            <CardContent className="py-10 text-center text-sm text-muted-foreground">
              No hay productos registrados en esta ubicación. Agrega nuevos
              artículos para comenzar a gestionarlos.
            </CardContent>
          </Card>
        )}
        {currentProducts.map((product) => {
          const status = getStockStatus(product);
          const StatusIcon = status.icon;

          return (
            <Card
              key={product.id}
              className={cn(
                "transition-all duration-200",
                status.color === "destructive" &&
                  "border-destructive/50 bg-destructive/5",
                status.color === "warning" && "border-warning/50 bg-warning/5"
              )}
            >
              <CardContent className="p-6">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                      <h3 className="text-xl font-semibold text-foreground">
                        {product.name}
                      </h3>
                      <span className="text-sm text-muted-foreground px-2 py-1 bg-muted rounded">
                        {product.category}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <StatusIcon
                        className={cn("h-4 w-4", `text-${status.color}`)}
                      />
                      <span
                        className={cn(
                          "text-sm font-medium",
                          `text-${status.color}`
                        )}
                      >
                        {status.label}
                      </span>
                      <span className="text-sm text-muted-foreground">
                        · Stock mínimo: {product.minStock}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <div className="text-center min-w-[80px]">
                      <p className="text-sm text-muted-foreground mb-1">
                        Stock actual
                      </p>
                      <p className="text-4xl font-bold text-primary">
                        {product.stock}
                      </p>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        onClick={() => updateStock(product.id, -1)}
                        disabled={product.stock === 0}
                        size="lg"
                        variant="outline"
                        className="h-14 w-14"
                      >
                        <Minus className="h-6 w-6" />
                      </Button>
                      <Button
                        onClick={() => updateStock(product.id, 1)}
                        size="lg"
                        className="h-14 w-14 bg-gradient-primary"
                      >
                        <Plus className="h-6 w-6" />
                      </Button>
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
