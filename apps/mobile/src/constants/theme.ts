/**
 * Below are the colors that are used in the app. The colors are defined in the light and dark mode.
 * There are many other ways to style your app. For example, [Nativewind](https://www.nativewind.dev/), [Tamagui](https://tamagui.dev/), [unistyles](https://reactnativeunistyles.vercel.app), etc.
 */

import '@/global.css';

import { Platform } from 'react-native';

export const Colors = {
  light: {
    text: '#0B1736',
    background: '#F4F8FF',
    backgroundElement: '#FFFFFF',
    backgroundSelected: '#DCEBFF',
    textSecondary: '#526584',
    border: '#DDE7F5',
    primary: '#0B5CFF',
    primaryDark: '#0A2E73',
    mint: '#72DDB8',
    amber: '#FFB52E',
    violet: '#A882F3',
    success: '#169B62',
  },
  dark: {
    text: '#F3F7FF',
    background: '#071126',
    backgroundElement: '#101E3B',
    backgroundSelected: '#173B75',
    textSecondary: '#A9BAD4',
    border: '#24375B',
    primary: '#5B94FF',
    primaryDark: '#AFCBFF',
    mint: '#72DDB8',
    amber: '#FFBE45',
    violet: '#B99AF6',
    success: '#59D69A',
  },
} as const;

export type ThemeColor = keyof typeof Colors.light & keyof typeof Colors.dark;

export const Fonts = Platform.select({
  ios: {
    /** iOS `UIFontDescriptorSystemDesignDefault` */
    sans: 'system-ui',
    /** iOS `UIFontDescriptorSystemDesignSerif` */
    serif: 'ui-serif',
    /** iOS `UIFontDescriptorSystemDesignRounded` */
    rounded: 'ui-rounded',
    /** iOS `UIFontDescriptorSystemDesignMonospaced` */
    mono: 'ui-monospace',
  },
  default: {
    sans: 'normal',
    serif: 'serif',
    rounded: 'normal',
    mono: 'monospace',
  },
  web: {
    sans: 'var(--font-display)',
    serif: 'var(--font-serif)',
    rounded: 'var(--font-rounded)',
    mono: 'var(--font-mono)',
  },
});

export const Spacing = {
  half: 2,
  one: 4,
  two: 8,
  three: 16,
  four: 24,
  five: 32,
  six: 64,
} as const;

export const BottomTabInset = Platform.select({ ios: 50, android: 80 }) ?? 0;
export const MaxContentWidth = 800;
