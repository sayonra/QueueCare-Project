import { createContext, PropsWithChildren, useContext, useMemo, useState } from 'react';

type Language = 'en' | 'km';
type CopyKey = keyof typeof messages.en;

const messages = {
  en: {
    home: 'Home', explore: 'Explore', tickets: 'Tickets', greeting: 'Good morning', smoother: 'Here for a smoother day.',
    yourQueue: 'Your queue', estimatedWait: 'Estimated wait', liveStatus: 'Live status · Tap to view your ticket',
    noTicket: 'No active ticket', noTicketCopy: 'Choose an open service and reserve your place remotely.', findBranch: 'Find a branch',
    popular: 'Popular services', seeAll: 'See all', openNow: 'Open now', closed: 'Closed', services: 'services',
    branchDiscovery: 'BRANCH DISCOVERY', nearestQueue: 'Find your nearest queue', search: 'Search branches or services', allBranches: 'All branches',
    shortestWait: 'Shortest wait', status: 'Status', visits: 'YOUR VISITS', yourTickets: 'Your tickets', noTickets: 'No tickets yet',
    noTicketsCopy: 'Your queue history will appear here.', signIn: 'Sign in', signInCopy: 'Sign in to view active and previous visits.',
  },
  km: {
    home: 'ទំព័រដើម', explore: 'ស្វែងរក', tickets: 'សំបុត្រ', greeting: 'អរុណសួស្តី', smoother: 'រង់ចាំតិចជាងមុន និងរស់នៅបានច្រើនជាងមុន។',
    yourQueue: 'ជួររបស់អ្នក', estimatedWait: 'ពេលរង់ចាំប៉ាន់ស្មាន', liveStatus: 'ស្ថានភាពផ្ទាល់ · ចុចមើលសំបុត្រ',
    noTicket: 'មិនមានសំបុត្រសកម្ម', noTicketCopy: 'ជ្រើសរើសសេវាកម្មដែលបើក ហើយកក់លេខរបស់អ្នក។', findBranch: 'ស្វែងរកសាខា',
    popular: 'សេវាកម្មពេញនិយម', seeAll: 'មើលទាំងអស់', openNow: 'កំពុងបើក', closed: 'បិទ', services: 'សេវាកម្ម',
    branchDiscovery: 'ស្វែងរកសាខា', nearestQueue: 'ស្វែងរកជួរដែលនៅជិតអ្នក', search: 'ស្វែងរកសាខា ឬសេវាកម្ម', allBranches: 'សាខាទាំងអស់',
    shortestWait: 'រង់ចាំខ្លីបំផុត', status: 'ស្ថានភាព', visits: 'ការមកកាន់របស់អ្នក', yourTickets: 'សំបុត្ររបស់អ្នក', noTickets: 'មិនទាន់មានសំបុត្រ',
    noTicketsCopy: 'ប្រវត្តិជួររបស់អ្នកនឹងបង្ហាញនៅទីនេះ។', signIn: 'ចូលប្រើ', signInCopy: 'ចូលប្រើដើម្បីមើលសំបុត្រសកម្ម និងការមកកាន់ពីមុន។',
  },
} as const;

type Preferences = { language: Language; toggleLanguage: () => void; t: (key: CopyKey) => string };
const PreferencesContext = createContext<Preferences | null>(null);

export function PreferencesProvider({ children }: PropsWithChildren) {
  const [language, setLanguage] = useState<Language>('en');
  const value = useMemo(() => ({ language, toggleLanguage: () => setLanguage((current) => current === 'en' ? 'km' : 'en'), t: (key: CopyKey) => messages[language][key] }), [language]);
  return <PreferencesContext.Provider value={value}>{children}</PreferencesContext.Provider>;
}

export function usePreferences() {
  const context = useContext(PreferencesContext);
  if (!context) throw new Error('usePreferences must be used inside PreferencesProvider.');
  return context;
}
