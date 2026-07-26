import type {
  GeometryDimension,
  LengthUnit,
} from "@/types/geometryCalculator";

export const LENGTH_UNITS: LengthUnit[] = ["mm", "cm", "m", "km", "in", "ft"];

const METERS_PER_UNIT: Record<LengthUnit, number> = {
  mm: 0.001,
  cm: 0.01,
  m: 1,
  km: 1_000,
  in: 0.0254,
  ft: 0.3048,
};

export function toBaseUnit(
  value: number,
  unit: LengthUnit,
  dimension: GeometryDimension,
): number {
  return value * METERS_PER_UNIT[unit] ** dimension;
}

export function fromBaseUnit(
  value: number,
  unit: LengthUnit,
  dimension: GeometryDimension,
): number {
  return value / METERS_PER_UNIT[unit] ** dimension;
}

export function unitLabel(
  unit: LengthUnit,
  dimension: GeometryDimension,
): string {
  if (dimension === 2) return `${unit}²`;
  if (dimension === 3) return `${unit}³`;
  return unit;
}
