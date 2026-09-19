import { Colors } from '@/constants/theme';
import { useSession } from '@/context/session';
import { apiRequest } from '@/lib/api';
import { Appointment, Branch, Service, Ticket } from '@/lib/types';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Text, useColorScheme, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function BranchDetailScreen() {
  const color = Colors[useColorScheme() === 'dark' ? 'dark' : 'light'];
  const { id } = useLocalSearchParams<{ id: string }>();
  const { token } = useSession();
  const [branch, setBranch] = useState<Branch | null>(null);
  const [selected, setSelected] = useState<Service | null>(null);
  const [loading, setLoading] = useState(true);
  const [joining, setJoining] = useState(false);
  const [scheduling, setScheduling] = useState(false);
  const [visitors, setVisitors] = useState(1);
  const [visitHour, setVisitHour] = useState(9);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!token) { router.replace('/login'); return; }
    let active = true;
    apiRequest<{ data: Branch }>(`/customer/branches/${id}`, token)
      .then((response) => { if (active) { setBranch(response.data); setSelected(response.data.services.find((service) => service.queue.is_open) ?? null); } })
      .catch((reason) => active && setError(reason.message))
      .finally(() => active && setLoading(false));
    return () => { active = false; };
  }, [id, token]);

  async function join() {
    if (!selected || !token) return;
    setJoining(true); setError('');
    try {
      await apiRequest<{ data: Ticket }>('/tickets', token, { method: 'POST', body: JSON.stringify({ service_id: selected.id, visitors_count: visitors }) });
      router.replace('/ticket');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not join this queue.'); }
    finally { setJoining(false); }
  }

  function confirmJoin() {
    if (!selected) return;
    const wait = selected.queue.estimated_wait_minutes === null ? 'Unavailable' : `${selected.queue.estimated_wait_minutes} minutes`;
    Alert.alert('Join this queue?', `${branch?.name}\n${selected.name}\n${visitors} visitor${visitors === 1 ? '' : 's'}\nEstimated wait: ${wait}`, [{ text: 'Not yet', style: 'cancel' }, { text: 'Join queue', onPress: join }]);
  }

  async function schedule() {
    if (!selected || !token) return;
    setScheduling(true); setError('');
    const visit = new Date(); visit.setDate(visit.getDate() + 1); visit.setHours(visitHour, 0, 0, 0);
    try {
      await apiRequest<{ data: Appointment }>('/appointments', token, { method: 'POST', body: JSON.stringify({ service_id: selected.id, scheduled_for: visit.toISOString(), visitors_count: visitors }) });
      router.replace('/ticket');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not schedule this visit.'); }
    finally { setScheduling(false); }
  }

  if (loading) return <SafeAreaView style={[styles.screen, styles.center, { backgroundColor: color.background }]}><ActivityIndicator color={color.primary} /></SafeAreaView>;
  if (!branch) return <SafeAreaView style={[styles.screen, styles.center, { backgroundColor: color.background }]}><Text style={styles.error}>{error || 'Branch unavailable.'}</Text></SafeAreaView>;

  return <SafeAreaView style={[styles.screen, { backgroundColor: color.background }]} edges={['bottom']}><ScrollView contentContainerStyle={styles.content}>
    <View style={[styles.hero, { backgroundColor: color.primaryDark }]}><Text style={styles.heroTag}>{branch.is_open ? '● OPEN NOW' : '● CLOSED'}</Text><Text style={styles.heroTitle}>{branch.name}</Text><Text style={styles.heroAddress}>{branch.address}</Text></View>
    <Text style={[styles.heading, { color: color.text }]}>Choose a service</Text><Text style={[styles.subtitle, { color: color.textSecondary }]}>Live waits update from the branch queue.</Text>
    {branch.services.map((service) => { const isSelected = selected?.id === service.id; return <Pressable disabled={!service.queue.is_open} onPress={() => setSelected(service)} key={service.id} style={[styles.service, { backgroundColor: color.backgroundElement, borderColor: isSelected ? color.primary : color.border, opacity: service.queue.is_open ? 1 : .55 }]}><View style={[styles.code, { backgroundColor: isSelected ? color.primary : color.backgroundSelected }]}><Text style={{ color: isSelected ? 'white' : color.primary, fontWeight: '900' }}>{service.code}</Text></View><View style={styles.serviceInfo}><Text style={[styles.serviceName, { color: color.text }]}>{service.name}</Text><Text style={[styles.serviceMeta, { color: color.textSecondary }]}>{service.queue.waiting_count} people waiting · {service.queue.active_counters} counters</Text></View><Text style={[styles.waitValue, { color: color.primaryDark }]}>{service.queue.estimated_wait_minutes === null ? '—' : `${service.queue.estimated_wait_minutes}m`}</Text></Pressable>; })}
    {error ? <Text style={styles.error}>{error}</Text> : null}
    <View style={[styles.booking, { backgroundColor: color.backgroundElement, borderColor: color.border }]}><View style={styles.bookingRow}><View><Text style={[styles.label, { color: color.textSecondary }]}>Visitors</Text><Text style={[styles.bookingValue, { color: color.text }]}>{visitors}</Text></View><View style={styles.stepper}><Pressable onPress={() => setVisitors((value) => Math.max(1, value - 1))} style={[styles.step, { backgroundColor: color.backgroundSelected }]}><Text style={{ color: color.primary, fontWeight: '900' }}>−</Text></Pressable><Pressable onPress={() => setVisitors((value) => Math.min(5, value + 1))} style={[styles.step, { backgroundColor: color.backgroundSelected }]}><Text style={{ color: color.primary, fontWeight: '900' }}>+</Text></Pressable></View></View><Text style={[styles.label, { color: color.textSecondary, marginTop: 13 }]}>Tomorrow appointment</Text><View style={styles.timeRow}>{[9, 14].map((hour) => <Pressable key={hour} onPress={() => setVisitHour(hour)} style={[styles.time, { backgroundColor: visitHour === hour ? color.primary : color.backgroundSelected }]}><Text style={{ color: visitHour === hour ? 'white' : color.primaryDark, fontWeight: '900' }}>{hour === 9 ? '9:00 AM' : '2:00 PM'}</Text></Pressable>)}</View></View>
    <Pressable disabled={!selected || joining || !branch.is_open} onPress={confirmJoin} style={[styles.join, { backgroundColor: color.primary, opacity: !selected || joining || !branch.is_open ? .45 : 1 }]}>{joining ? <ActivityIndicator color="white" /> : <Text style={styles.joinText}>Join queue now</Text>}</Pressable>
    <Pressable disabled={!selected || scheduling} onPress={schedule} style={[styles.schedule, { borderColor: color.primary }]}>{scheduling ? <ActivityIndicator color={color.primary} /> : <Text style={[styles.scheduleText, { color: color.primary }]}>Schedule tomorrow</Text>}</Pressable>
    <Text style={[styles.note, { color: color.textSecondary }]}>Groups can include up to five visitors. Scheduled check-in opens 30 minutes before and closes 15 minutes after the visit time.</Text>
  </ScrollView></SafeAreaView>;
}

const styles = StyleSheet.create({ screen: { flex: 1 }, center: { alignItems: 'center', justifyContent: 'center' }, content: { padding: 18, paddingBottom: 40 }, hero: { borderRadius: 25, padding: 22 }, heroTag: { color: '#72DDB8', fontSize: 10, fontWeight: '900', letterSpacing: 1 }, heroTitle: { color: 'white', fontSize: 28, fontWeight: '900', marginTop: 12 }, heroAddress: { color: '#C8D7F0', fontSize: 13, lineHeight: 20, marginTop: 7 }, heading: { fontSize: 21, fontWeight: '900', marginTop: 25 }, subtitle: { fontSize: 13, marginTop: 4, marginBottom: 14 }, service: { borderWidth: 1.5, borderRadius: 19, padding: 13, flexDirection: 'row', alignItems: 'center', marginBottom: 10 }, code: { width: 44, height: 44, borderRadius: 14, alignItems: 'center', justifyContent: 'center' }, serviceInfo: { flex: 1, marginLeft: 12 }, serviceName: { fontSize: 14, fontWeight: '900' }, serviceMeta: { fontSize: 10, marginTop: 4 }, waitValue: { fontSize: 17, fontWeight: '900' }, booking: { borderWidth: 1, borderRadius: 20, padding: 15, marginTop: 8 }, bookingRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }, label: { fontSize: 10 }, bookingValue: { fontSize: 20, fontWeight: '900', marginTop: 2 }, stepper: { flexDirection: 'row', gap: 8 }, step: { width: 40, height: 40, borderRadius: 13, alignItems: 'center', justifyContent: 'center' }, timeRow: { flexDirection: 'row', gap: 8, marginTop: 8 }, time: { flex: 1, height: 40, borderRadius: 13, alignItems: 'center', justifyContent: 'center' }, join: { height: 53, borderRadius: 17, alignItems: 'center', justifyContent: 'center', marginTop: 14 }, joinText: { color: 'white', fontSize: 16, fontWeight: '900' }, schedule: { height: 53, borderRadius: 17, alignItems: 'center', justifyContent: 'center', marginTop: 10, borderWidth: 1.5 }, scheduleText: { fontSize: 15, fontWeight: '900' }, note: { fontSize: 11, textAlign: 'center', marginTop: 11, lineHeight: 17 }, error: { color: '#B42345', backgroundColor: '#FFF0F3', borderRadius: 12, padding: 12, marginTop: 10 } });
