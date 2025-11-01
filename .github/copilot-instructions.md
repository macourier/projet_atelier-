<!--
Guidance for AI coding agents working in this repository.
If this file already exists, merge carefully: preserve hand-authored sections and append any new heuristics below.
-->

# Quick start (always do these first)

1. Start with a one-line goal from the human ("Cline: Start with a goal"). If missing, ask a clarifying question before editing code.
2. Locate the project type by probing files: `package.json`, `pyproject.toml`, `requirements.txt`, `Cargo.toml`, `pom.xml`, `.sln`/`.csproj`, `setup.py`, `Makefile`, `Dockerfile`, `README.md`, and `.github/workflows/`.
3. Open `README.md` (if present) and any `docs/` or `architecture/` files for high-level intent before making changes.

## Big-picture heuristics

- If you see `package.json` -> treat as Node/JS/TS project. Look for `scripts` (build/test/lint). Prefer `npm run <script>` or `pnpm`/`yarn` if lockfiles exist (`pnpm-lock.yaml`, `yarn.lock`).
- If you see `pyproject.toml` or `requirements.txt` -> Python. Prefer `python -m pytest` if tests exist, and `pip install -r requirements.txt` or `pip install -e .` for local installs.
- If you see `Cargo.toml` -> Rust. Use `cargo build` / `cargo test`.
- If you see `.sln` or `.csproj` -> .NET. Use `dotnet build` / `dotnet test`.
- If you see `pom.xml` -> Java (Maven). Use `mvn -q test`.

When multiple ecosystems exist, find the code under `src/`, `packages/`, `services/` or `backend/` to determine the primary language.

## Developer workflows & commands (detect and use)

- Building: prefer the project's scripted command (e.g., `npm run build`, `make build`, `dotnet build`, `cargo build`). If none, run the language default build.
- Testing: prefer `npm test` / `pytest -q` / `cargo test` / `dotnet test` / `mvn -q test` based on detected files.
- Linting/formatting: look for config files (`.eslintrc`, `pyproject.toml` with tool.poetry/black, `rustfmt.toml`). Use the project's configured commands.

Examples (PowerShell-friendly):

```powershell
# Node: install and run tests
npm ci
npm test

# Python: create venv, install, test
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python -m pytest -q
```

## Project-specific conventions to check (look for these files/places)

- `src/` — primary source code root in many projects.
- `packages/` or `services/` — monorepo packages; inspect each package's `package.json`/`pyproject.toml`.
- `tests/` or `__tests__/` — unit tests; use these to infer behavior and write tests in the same style.
- `.github/workflows/` — CI steps often show exact build/test/lint commands.
- `docker/` or `Dockerfile` — runtime and integration requirements.

When suggesting code changes, mirror the project's import/require style, error handling patterns, and test patterns (naming, fixtures).

## Integration points & external dependencies

- Look for `ENV` or `.env` usage, `secrets` in workflow files, external API client wrappers in `lib/` or `clients/`.
- If a `Makefile`, `docker-compose.yml`, or `compose/` exists, prefer those commands for multi-service integration tests.

## Small examples to follow (when present)

- If tests use fixtures (e.g., pytest fixtures or Jest setupFiles), add tests that reuse the same fixtures.
- If the project uses `async/await` across code, avoid mixing callbacks — keep the same async style.

## Merge guidance (if `.github/copilot-instructions.md` already existed)

- Preserve any hand-authored sections verbatim. Append or update a short "Agent heuristics" section with the date and a short delta.
- Keep the file concise: aim for ~20–50 lines of actionable steps.

## When you can't discover something

- If build/test commands or the primary language can't be detected, ask the human for the goal and the desired runtime, or request a sample file to inspect.

---
If this file doesn't match the repository contents, tell me what files are present (e.g., `package.json`, `pyproject.toml`) and I will regenerate a tailored version. Ready for feedback.
