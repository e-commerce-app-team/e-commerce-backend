## Advanced Agentic FullStack Pipeline
For every new feature or task, you must execute the following structured pipeline, utilizing specialized subagents when necessary:

### 1. Phase 1: Architecture & Requirements (The "Architect" Phase)
- **Action:** Analyze the request, explore the current state of both Flutter and Laravel, and identify integration points. Use a `Codebase Researcher` subagent if the system is complex.
- **Output:** Define clear Functional & Non-functional requirements, Database schemas, API Contracts (Endpoints & JSON structure), and State Management flow.
- **Logical Check:** Critically evaluate the user's initial idea. If there is a better, more secure, or more scalable approach, you MUST propose it before writing any code.

### 2. Phase 2: Backend Execution (The "Laravel Expert" Phase)
- **Action:** Implement the backend first. Create/update Migrations, Models, FormRequests, and Controllers. 
- **Focus:** Ensure security (Authentication/Authorization), performance (Eager Loading), and robust error handling.
- **Rule:** Never modify the frontend until the backend API contract is finalized and verified.

### 3. Phase 3: Frontend Execution (The "Flutter Expert" Phase)
- **Action:** Implement the UI and connect it to the new Backend APIs.
- **Focus:** Clean architecture, proper state management (GetX), handling loading/error states gracefully, and premium UI/UX design.

### 4. Phase 4: Integration & Quality Assurance (The "QA Reviewer" Phase)
- **Action:** Exhaustively trace the data flow from the Flutter UI to the Laravel Database and back.
- **Focus:** Test edge cases (e.g., token expiration, empty states, validation failures, network errors).

### 5. Phase 5: Transparent Reporting
- **Action:** Immediately after implementation, provide a detailed, formatted summary of all modified files across both repositories. Explain *why* changes were made, not just *what* was changed.

## Strict Flutter UI Standards (Localization & Theming)
When modifying or creating any User Interface (UI) component or screen in the Flutter frontend, you MUST adhere to the following strict standards:
1. **Strict Localization (No Hardcoded Strings)**: You are strictly forbidden from using hardcoded text strings. Every single user-facing text must support Arabic and English localization. Always add the new keys to the translation files and use the appropriate translation method (e.g., `'your_key'.tr`).
2. **Centralized Colors (No Hardcoded Colors)**: Never use raw colors (e.g., `Colors.red` or `Color(0xFF...)`). All colors MUST be referenced directly from the project's central color class (e.g., `AppColor`).
3. **Centralized Typography**: All text styles and fonts must be imported and used from the project's central text styling class/theme, ensuring uniform typography across the app.
