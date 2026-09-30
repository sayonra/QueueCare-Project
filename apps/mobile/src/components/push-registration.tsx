import { useSession } from '@/context/session';
import { apiRequest } from '@/lib/api';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { useEffect } from 'react';
import { Platform } from 'react-native';

const isExpoGo = Constants.appOwnership === 'expo';

if (!isExpoGo) {
  try {
    Notifications.setNotificationHandler({
      handleNotification: async () => ({
        shouldShowBanner: true,
        shouldShowList: true,
        shouldPlaySound: true,
        shouldSetBadge: false,
      }),
    });
  } catch {
    // Gracefully handle environments without push notification support
  }
}

export function PushRegistration() {
  const { token } = useSession();

  useEffect(() => {
    if (isExpoGo || !token || !Device.isDevice || Platform.OS === 'web') return;
    let active = true;
    async function register() {
      try {
        if (Platform.OS === 'android') {
          await Notifications.setNotificationChannelAsync('queue-updates', {
            name: 'Queue updates',
            importance: Notifications.AndroidImportance.HIGH,
          });
        }
        const current = await Notifications.getPermissionsAsync();
        const permission = current.granted ? current : await Notifications.requestPermissionsAsync();
        if (!permission.granted || !active) return;
        const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
        if (!projectId) return;
        const pushToken = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
        if (!active) return;
        await apiRequest('/device-tokens', token, {
          method: 'POST',
          body: JSON.stringify({ token: pushToken, platform: Platform.OS }),
        });
      } catch {
        // Ignore push token error in unsupported environments
      }
    }
    void register().catch(() => undefined);
    return () => { active = false; };
  }, [token]);

  return null;
}
