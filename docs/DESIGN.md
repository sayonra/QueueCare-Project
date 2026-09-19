# QueueCare design direction

## Selected concept

**Concept 11 — Modular Bento** is the approved visual direction for the QueueCare platform. Apply the same design language to the customer mobile app, staff tablet interface, admin portal, and public display, adapting density and navigation for each surface.

The intended character is **modular, friendly, and confident**. The interface should feel optimistic and easy to scan while keeping queue status and the next action immediately clear.

## Visual system

- Use a bright cobalt blue as the primary action and active-navigation color.
- Use deep navy for headings, ticket numbers, and high-emphasis text.
- Use pale blue surfaces and soft blue page backgrounds to separate modules.
- Use mint, amber, and violet as restrained accents for service categories, illustrations, charts, and status details.
- Keep success green, warning amber, danger red, and neutral gray semantically consistent. Do not use accent colors as competing primary actions.
- Prefer white or lightly tinted cards with subtle borders, soft shadows, and rounded corners.
- Use a clean sans-serif typeface, strong numeric hierarchy, compact labels, and plain language.

Initial design tokens:

| Token | Value | Use |
| --- | --- | --- |
| Primary | `#0B5CFF` | Main actions, active navigation, links |
| Primary dark | `#0A2E73` | Headings and ticket numbers |
| Primary soft | `#DCEBFF` | Selected and informational surfaces |
| Canvas | `#F4F8FF` | App background |
| Surface | `#FFFFFF` | Cards and navigation surfaces |
| Mint accent | `#72DDB8` | Positive category/chart accent |
| Amber accent | `#FFB52E` | Attention and illustration accent |
| Violet accent | `#A882F3` | Secondary category/chart accent |
| Success | `#169B62` | Open, complete, available |
| Text | `#0B1736` | Primary text |
| Text muted | `#526584` | Secondary text |
| Border | `#DDE7F5` | Dividers and card outlines |

These values are the starting implementation tokens. Check contrast and tune them during component implementation while preserving the selected concept.

## Layout principles

1. Compose screens from self-contained bento modules rather than long undifferentiated panels.
2. Give the active ticket the strongest hierarchy: ticket number, people ahead, estimated wait, and progress appear together.
3. Keep one dominant action per module, using a solid blue button and clear verb such as **Join queue** or **Call next**.
4. Use compact side-by-side cards when space allows, such as nearby branches, counters, and dashboard metrics.
5. Keep touch targets at least 44 points and maintain generous spacing around status-changing actions.
6. Use icons with text labels for navigation and operational controls; color alone must not communicate status.
7. Support light and dark themes from shared semantic tokens rather than hardcoded component colors.

## Surface application

### Customer mobile

- Four-item bottom navigation: Home, Tickets, History, Profile.
- Greeting and small positive illustration may introduce the page without competing with the active ticket.
- Active ticket is the first operational card when present.
- Nearby branches use compact modular cards with distance and open status.

### Staff and admin web

- Translate the bento system into a responsive grid with a collapsible sidebar.
- Use larger modules for waiting lists and queue operations; use smaller modules for metrics, counter state, and activity.
- Keep destructive and irreversible status actions visually distinct from the blue primary action.
- Preserve the same radius, typography, semantic colors, and icon style used by mobile.

### Public display

- Use the same navy and cobalt identity with a much larger type scale.
- Reduce decoration and show only serving numbers, counters, upcoming numbers, and branch context.

## Component priorities

Build these shared patterns first: button, icon button, status badge, surface card, metric card, ticket card, progress rail, branch card, empty state, skeleton, navigation item, dialog, toast, and form field. Document states for default, pressed, focused, disabled, loading, success, warning, and error.
