import { Colors } from '@/constants/theme';
import { useSession } from '@/context/session';
import { router } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, useColorScheme, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function LoginScreen() {
  const color = Colors[useColorScheme() === 'dark' ? 'dark' : 'light'];
  const { signIn } = useSession();
  const [email, setEmail] = useState('customer@queuecare.test');
  const [password, setPassword] = useState('password');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function submit() {
    setBusy(true); setError('');
    try { await signIn(email.trim(), password); router.replace('/'); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Could not sign in.'); }
    finally { setBusy(false); }
  }

  return <SafeAreaView style={[styles.screen, { backgroundColor: color.background }]}><KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.container}><View style={[styles.logo, { backgroundColor: color.primary }]}><Text style={styles.logoText}>Q</Text></View><Text style={[styles.title, { color: color.text }]}>Welcome back</Text><Text style={[styles.subtitle, { color: color.textSecondary }]}>Sign in to reserve and follow your queue.</Text><Text style={[styles.label, { color: color.text }]}>Email</Text><TextInput value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" style={[styles.input, { backgroundColor: color.backgroundElement, borderColor: color.border, color: color.text }]} /><Text style={[styles.label, { color: color.text }]}>Password</Text><TextInput value={password} onChangeText={setPassword} secureTextEntry style={[styles.input, { backgroundColor: color.backgroundElement, borderColor: color.border, color: color.text }]} />{error ? <Text style={styles.error}>{error}</Text> : null}<Pressable disabled={busy} onPress={submit} style={[styles.button, { backgroundColor: color.primary }]}>{busy ? <ActivityIndicator color="white" /> : <Text style={styles.buttonText}>Sign in</Text>}</Pressable><View style={[styles.demo, { backgroundColor: color.backgroundSelected }]}><Text style={[styles.demoTitle, { color: color.primaryDark }]}>Customer demo</Text><Text style={[styles.demoText, { color: color.textSecondary }]}>customer@queuecare.test · password</Text></View></KeyboardAvoidingView></SafeAreaView>;
}

const styles = StyleSheet.create({ screen: { flex: 1 }, container: { flex: 1, justifyContent: 'center', padding: 26 }, logo: { width: 54, height: 54, borderRadius: 19, alignItems: 'center', justifyContent: 'center' }, logoText: { color: 'white', fontSize: 27, fontWeight: '900' }, title: { fontSize: 34, fontWeight: '900', marginTop: 28, letterSpacing: -1 }, subtitle: { fontSize: 15, marginTop: 7, marginBottom: 26 }, label: { fontSize: 13, fontWeight: '800', marginBottom: 8, marginTop: 14 }, input: { height: 50, borderWidth: 1, borderRadius: 15, paddingHorizontal: 15, fontSize: 15 }, button: { height: 52, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginTop: 24 }, buttonText: { color: 'white', fontSize: 16, fontWeight: '900' }, error: { color: '#B42345', backgroundColor: '#FFF0F3', borderRadius: 12, padding: 12, marginTop: 14, fontSize: 13 }, demo: { borderRadius: 16, padding: 15, marginTop: 22 }, demoTitle: { fontSize: 13, fontWeight: '900' }, demoText: { marginTop: 3, fontSize: 12 } });
