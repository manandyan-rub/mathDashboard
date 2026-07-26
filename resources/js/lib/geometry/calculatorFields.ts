import type { CalculatorFieldStatus } from "@/types/geometryCalculator";

type CalculatorFieldStatusInput = {
  isEntered: boolean;
  isValid: boolean;
  isConflicting: boolean;
  hasCalculatedValue: boolean;
};

export function resolveCalculatorFieldStatus({
  isEntered,
  isValid,
  isConflicting,
  hasCalculatedValue,
}: CalculatorFieldStatusInput): CalculatorFieldStatus {
  if (isEntered && !isValid) return "invalid";
  if (isEntered && isConflicting) return "conflicting";
  if (isEntered) return "entered";
  if (hasCalculatedValue) return "calculated";
  return "empty";
}
