import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export type Location = "santas" | "outdoor" | "kiddo";

interface LocationSelectorProps {
  selectedLocation: Location;
  onLocationChange: (location: Location) => void;
}

export function LocationSelector({
  selectedLocation,
  onLocationChange,
}: LocationSelectorProps) {
  const locations: { value: Location; label: string; color: string }[] = [
    { value: "santas", label: "Santas Indoor", color: "bg-primary" },
    { value: "outdoor", label: "Outdoor", color: "bg-accent" },
    { value: "kiddo", label: "Kiddo", color: "bg-success" },
  ];

  return (
    <div className="flex flex-wrap gap-2">
      {locations.map((location) => (
        <Button
          key={location.value}
          onClick={() => onLocationChange(location.value)}
          variant={selectedLocation === location.value ? "default" : "outline"}
          className={cn(
            "min-w-[120px] font-bold text-base transition-all duration-200",
            selectedLocation === location.value && "shadow-glow-primary"
          )}
        >
          <span className={cn("w-2 h-2 rounded-full mr-2", location.color)} />
          {location.label}
        </Button>
      ))}
    </div>
  );
}
