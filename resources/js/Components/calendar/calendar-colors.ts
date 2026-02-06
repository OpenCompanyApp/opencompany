export const calendarColors = ['blue', 'green', 'red', 'purple', 'yellow', 'orange', 'pink', 'indigo'] as const

export type CalendarColor = (typeof calendarColors)[number]

export const calendarColorHex: Record<CalendarColor, string> = {
  blue: '#3b82f6',
  green: '#22c55e',
  red: '#ef4444',
  purple: '#a855f7',
  yellow: '#eab308',
  orange: '#f97316',
  pink: '#ec4899',
  indigo: '#6366f1',
}

export const calendarColorClasses: Record<CalendarColor, string> = {
  blue: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-900/60',
  green: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/60',
  red: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/60',
  purple: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-900/60',
  yellow: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-900/60',
  orange: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 hover:bg-orange-200 dark:hover:bg-orange-900/60',
  pink: 'bg-pink-100 dark:bg-pink-900/40 text-pink-700 dark:text-pink-300 hover:bg-pink-200 dark:hover:bg-pink-900/60',
  indigo: 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-900/60',
}

export function getCalendarColorHex(color?: string): string {
  return calendarColorHex[(color || 'blue') as CalendarColor] || calendarColorHex.blue
}

export function getCalendarColorClasses(color?: string): string {
  return calendarColorClasses[(color || 'blue') as CalendarColor] || calendarColorClasses.blue
}
