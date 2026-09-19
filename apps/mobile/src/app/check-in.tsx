import { Colors } from '@/constants/theme';
import { useSession } from '@/context/session';
import { apiRequest } from '@/lib/api';
import { Ticket } from '@/lib/types';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, useColorScheme, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function CheckInScreen() {
  const color = Colors[useColorScheme() === 'dark' ? 'dark' : 'light'];
  const { token } = useSession();
  const [permission, requestPermission] = useCameraPermissions();
  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [scanned, setScanned] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!token) return;
    apiRequest<{ data: Ticket }>('/tickets/active', token).then((response) => setTicket(response.data)).catch((reason) => setError(reason.message));
  }, [token]);

  async function checkIn(data: string) {
    if (!ticket?.appointment || !token || scanned) return;
    const expected = `queuecare://branch/${ticket.branch.id}`;
    if (data !== expected && data !== ticket.appointment.check_in_token) {
      setScanned(true); setError('This QR code belongs to a different branch.'); return;
    }
    setScanned(true); setError('');
    try {
      const response = await apiRequest<{ data: Ticket }>(`/tickets/${ticket.id}/check-in`, token, {
        method: 'POST', body: JSON.stringify({ check_in_token: ticket.appointment.check_in_token }),
      });
      if (response.data.status === 'cancelled') setError('The arrival window closed 15 minutes after the appointment. This visit is recorded as missed.');
      else router.replace('/ticket');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Check-in failed.'); }
  }

  if (!permission || !ticket) return <SafeAreaView style={[styles.screen, styles.center, { backgroundColor: color.background }]}><ActivityIndicator color={color.primary}/><Text style={[styles.helper, { color: color.textSecondary }]}>{error || 'Preparing secure check-in…'}</Text></SafeAreaView>;
  if (!permission.granted) return <SafeAreaView style={[styles.screen, styles.center, { backgroundColor: color.background }]}><View style={[styles.card, { backgroundColor: color.backgroundElement }]}><Text style={[styles.title, { color: color.text }]}>Camera access</Text><Text style={[styles.helper, { color: color.textSecondary }]}>QueueCare uses the camera only to read the branch QR code.</Text><Pressable onPress={requestPermission} style={[styles.button, { backgroundColor: color.primary }]}><Text style={styles.buttonText}>Allow camera</Text></Pressable></View></SafeAreaView>;

  return <SafeAreaView style={[styles.screen, { backgroundColor: '#06132F' }]} edges={['bottom']}><View style={styles.header}><Text style={styles.eyebrow}>ARRIVAL CHECK-IN</Text><Text style={styles.headerTitle}>{ticket.branch.name}</Text><Text style={styles.headerCopy}>Scan the QR sign at the branch. Your appointment code stays private.</Text></View><View style={styles.cameraWrap}><CameraView style={StyleSheet.absoluteFill} facing="back" barcodeScannerSettings={{ barcodeTypes: ['qr'] }} onBarcodeScanned={scanned ? undefined : ({ data }) => void checkIn(data)}/><View style={styles.frame}/></View><View style={styles.footer}><Text style={styles.ticket}>{ticket.number} · {new Date(ticket.appointment!.scheduled_for).toLocaleString()}</Text>{error ? <Text style={styles.error}>{error}</Text> : null}{scanned && error ? <Pressable onPress={() => { setScanned(false); setError(''); }} style={styles.retry}><Text style={styles.retryText}>Scan again</Text></Pressable> : null}</View></SafeAreaView>;
}

const styles = StyleSheet.create({ screen: { flex: 1 }, center: { alignItems: 'center', justifyContent: 'center', padding: 22 }, header: { padding: 22, paddingTop: 18 }, eyebrow: { color: '#72DDB8', fontSize: 10, fontWeight: '900', letterSpacing: 1.5 }, headerTitle: { color: 'white', fontSize: 25, fontWeight: '900', marginTop: 8 }, headerCopy: { color: '#AFC4E5', fontSize: 12, lineHeight: 18, marginTop: 6 }, cameraWrap: { flex: 1, overflow: 'hidden', marginHorizontal: 18, borderRadius: 28, alignItems: 'center', justifyContent: 'center' }, frame: { width: 230, height: 230, borderRadius: 28, borderWidth: 4, borderColor: '#72DDB8', backgroundColor: 'transparent' }, footer: { padding: 20, minHeight: 126 }, ticket: { color: 'white', textAlign: 'center', fontWeight: '800', fontSize: 12 }, error: { color: '#FFD0DA', backgroundColor: '#47172A', padding: 11, borderRadius: 12, marginTop: 10, textAlign: 'center' }, retry: { height: 44, borderRadius: 14, backgroundColor: 'white', justifyContent: 'center', marginTop: 10 }, retryText: { textAlign: 'center', color: '#0B5CFF', fontWeight: '900' }, card: { borderRadius: 25, padding: 22, width: '100%' }, title: { fontSize: 23, fontWeight: '900' }, helper: { fontSize: 13, textAlign: 'center', marginTop: 10, lineHeight: 19 }, button: { height: 50, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginTop: 18 }, buttonText: { color: 'white', fontWeight: '900' } });
