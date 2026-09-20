import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useColorScheme } from 'react-native';

import { AnimatedSplashOverlay } from '@/components/animated-icon';
import { SessionProvider } from '@/context/session';
import { PushRegistration } from '@/components/push-registration';
import { PreferencesProvider } from '@/context/preferences';

SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const colorScheme = useColorScheme();
  return (
    <PreferencesProvider>
      <SessionProvider>
        <PushRegistration />
        <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
          <AnimatedSplashOverlay />
          <Stack>
            <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
            <Stack.Screen name="login" options={{ title: 'Sign in', presentation: 'modal' }} />
            <Stack.Screen name="branch/[id]" options={{ title: 'Branch details' }} />
            <Stack.Screen name="ticket" options={{ title: 'Your ticket' }} />
            <Stack.Screen name="check-in" options={{ title: 'QR check-in', presentation: 'modal' }} />
          </Stack>
        </ThemeProvider>
      </SessionProvider>
    </PreferencesProvider>
  );
}
