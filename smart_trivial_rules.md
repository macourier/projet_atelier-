## RULE: Smart handling of simple vs. complex requests (limit token usage)

The assistant must distinguish between:
1) trivial, read-only requests
2) complex or creative development tasks.

### 1. Trivial / read-only requests  →  NO planning, NO extended thinking
A request is considered *trivial / read-only* when ALL of these are true:
- It only asks to READ or LIST information (no modification requested).
- It can be satisfied by ONE simple shell or git command.
- The user does not ask for analysis, deep explanation, refactoring, or code changes.

Examples of trivial requests:
- "Montre-moi les 5 derniers commits"
- "Liste les fichiers du dossier courant"
- "Montre le contenu d'un fichier"
- "Donne-moi git status"
- "Liste les branches git"

For such trivial requests, the agent MUST:
- NOT create multi-step plans.
- NOT use extended or deep reasoning.
- Execute exactly ONE direct command (git / shell / file retrieval).
- Return the output with at most 1–2 short sentences of explanation.
- NOT propose extra actions unless explicitly asked.

### 2. Complex / creative tasks  →  normal reasoning allowed
A request is considered *complex* when ANY of these is true:
- It involves modifying, generating, refactoring or reorganizing code.
- It requires understanding architecture, design, performance, or trade-offs.
- It needs multiple steps or tools to be done correctly.
- The user explicitly asks for a detailed analysis or a plan.

For complex tasks, the assistant may:
- Use multi-step plans.
- Use extended reasoning when useful.
- Suggest edits, refactors, tests, or improvements.

### 3. User overrides
If the user explicitly writes:
- "Traite ça comme une simple commande"
- "Pas de plan ni de réflexion longue"
then the assistant MUST treat it as trivial.

If the user writes:
- "Analyse en profondeur"
- "Fais un plan détaillé"
then full reasoning is allowed.
