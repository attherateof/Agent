# MageStack_Agent

A development-only Magento 2 / Mage-OS module that adds `bin/magento` commands for a coding agent
and direct question mode. Both commands talk to an [Ollama](https://ollama.com) model (default:
`qwen2.5-coder:14b`); agent mode gives it
workspace-scoped file tools — read, write, edit, search, list files, validate PHP/XML syntax, and view
`git diff` — so it can explore and modify **this Magento installation** on your behalf.

**Development-only.** Both commands refuse to run unless Magento is in `developer` mode. This is a
runtime guard, not a replacement for excluding the package from production deployments.

---

## 1. Purpose

Local coding agents (Cursor, Copilot agent mode, Claude Code, etc.) are convenient but usually
mean sending your codebase to a third-party API. `MageStack_Agent` gives the same "agent loop"
experience — describe a task in plain English, the model reads/searches/edits files and runs
commands until it's done — using an Ollama model at the configured `OLLAMA_HOST`, with:

- **Workspace-scoped file access**: file tools reject traversal and symlinks, deny protected
  configuration files, and skip configured generated/vendor directories during search.
- **Narrow terminal capability**: the terminal tool only runs `php -l` on PHP files or checks XML
  well-formedness inside the workspace; it cannot run arbitrary shell commands or PHP scripts.
- **Human-in-the-loop by default**: every write, edit or PHP syntax check asks `[y/N]` before it
  happens, unless you pass `--yes`.
- **Endpoint-aware data handling**: conversation and tool output are sent to `OLLAMA_HOST`; the
  default is intended for local Ollama, but a configured remote host can receive them.
- **Magento-aware prompting**: the model is told this is a Magento 2 install, which custom
  modules exist under `app/code`, and the core conventions to follow (DI over ObjectManager,
  plugins over rewrites, service contracts, etc.).
- **A single entry point that only works in developer mode**, refusing Magento's default and
  production modes. Developer mode alone does not establish that a store is non-live.

---

## 2. File structure

```
app/code/MageStack/Agent/
├── registration.php                  Registers the module with Magento's ComponentRegistrar
├── composer.json                     Module metadata (magento2-module package)
├── README.md                         This file
├── etc/
│   ├── adminhtml/system.xml           Admin settings for Ollama
│   ├── acl.xml                        Admin configuration ACL
│   ├── config.xml                     Default Ollama settings
│   ├── di.xml                         CLI registration and service preferences
│   └── module.xml                     Module declaration
├── Api/
│   ├── AgentSessionInteractionInterface.php
│   ├── OllamaConfigInterface.php
│   ├── OllamaConfigurationInterface.php
│   └── ToolInterface.php
├── Console/
│   ├── AgentConsolePresenter.php
│   └── Command/
│       ├── AskCommand.php
│       └── RunCommand.php
└── Model/
    ├── Agent/
    │   ├── Agent.php
    │   ├── AgentRuntimeBuilder.php
    │   ├── Planner.php
    │   ├── ToolExecutor.php
    │   └── ToolSetBuilder.php
    ├── Context/
    │   ├── CodebaseContext.php
    │   └── MagentoContext.php
    ├── Llm/
    │   └── OllamaClient.php
    ├── Service/
    │   ├── AgentConfigurationProvider.php
    │   ├── AgentSessionRequest.php
    │   ├── AgentSessionService.php
    │   ├── AskService.php
    │   ├── OllamaConfig.php
    │   └── OllamaConfigurationProvider.php
    └── Tool/
        ├── AbstractTool.php
      ├── DeleteFileTool.php
        ├── EditFileTool.php
        ├── GitDiffTool.php
        ├── ListFilesTool.php
        ├── ReadFileTool.php
        ├── SearchCodeTool.php
        ├── TerminalTool.php
        └── WriteFileTool.php
```

### What each file is doing

| File | Responsibility |
|---|---|
| `registration.php` | Tells Magento's component registrar this module exists at this path. Required for Magento to discover `app/code/MageStack/Agent`. |
| `etc/module.xml` | Standard module declaration — name and setup version. No `<sequence>`, since the module is CLI-only and doesn't depend on other modules loading first. |
| `etc/di.xml` | Registers both commands and declares a dedicated Monolog logger. The logger is injected into the services that perform agent/session work. |
| `Console/Command/RunCommand.php` | Thin Symfony CLI adapter: declares options, creates a session request and Symfony interaction adapter through generated factories, delegates to `AgentSessionService`, and maps its result to a CLI status. |
| `Console/AgentConsolePresenter.php` | Implements `Api\AgentSessionInteractionInterface` using Symfony input/output and `QuestionHelper`; formats approval previews and model/tool events. |
| `Console/Command/AskCommand.php` | Thin CLI adapter for `magestack:agent:ask`; delegates question validation and Ollama execution to `AskService`. |
| `Model/Agent/Agent.php` | The actual loop: send the conversation to Ollama → parse the JSON reply → either call a tool and feed the result back, or return the final answer. Caps steps at 15, trims history to fit the context window, and aborts after 3 unparseable replies in a row or after seeing the same tool call repeated. |
| `Model/Agent/Planner.php` | Builds the system prompt (base instructions + tool list + Magento/codebase context) and parses the model's JSON reply, including recovery from stray text or code fences around the JSON. |
| `Model/Agent/ToolExecutor.php` | Looks up the requested tool by name, asks for approval if the tool requires it, runs it, catches any exception so a single failing tool never crashes the agent, truncates very long output, and logs failures. |
| `Model/Agent/AgentRuntimeBuilder.php` / `ToolSetBuilder.php` | Compose the per-invocation agent and workspace-bound tools using Magento-generated factories, passing runtime workspace/model/approval settings explicitly. |
| `Model/Service/AgentConfigurationProvider.php` | Enforces developer mode and resolves the workspace; delegates common host/model/context settings to `OllamaConfigurationInterface`. |
| `Model/Service/OllamaConfig.php` | Implements `Api\OllamaConfigInterface` and reads persisted Admin settings through Magento's `ScopeConfigInterface`. |
| `Model/Service/AgentSessionService.php` | Builds the agent through `AgentRuntimeBuilder` and coordinates tasks through `AgentSessionRequest` and the `Api\AgentSessionInteractionInterface` port, without depending on Symfony classes. |
| `Model/Service/AskService.php` | Validates questions and sends them through `OllamaClientFactory`; shares host/model/context resolution with the agent through `OllamaConfigurationInterface` and defaults to a 2,048-token context. |
| `Model/Service/OllamaConfigurationProvider.php` | Implements `Api\OllamaConfigurationInterface`, calls `OllamaConfigInterface`, and resolves defaults, environment/CLI overrides, and context-size validation. |
| `Api/ToolInterface.php` | Public contract implemented by every agent tool. |
| `Api/AgentSessionInteractionInterface.php` | Application port used by `AgentSessionService`; the console presenter implements it. |
| `Api/OllamaConfigurationInterface.php` | Public contract for effective agent- and ask-mode Ollama settings. |
| `Api/OllamaConfigInterface.php` | Contract for reading persisted host, model, and per-mode context settings. |
| `Model/Llm/OllamaClient.php` | Sends the message history to `POST {OLLAMA_HOST}/api/chat` with `"format": "json"` (see §3) and returns the assistant's reply text. |
| `Model/Context/MagentoContext.php` | Detects whether the workspace is a Magento install (`bin/magento` present) or a standalone module (`etc/module.xml`), reads the Magento edition/version from `composer.json`, lists custom modules under `app/code`, and appends a fixed block of Magento coding conventions. |
| `Model/Context/CodebaseContext.php` | Lists top-level entries and a rough count of files by extension, so the model has a mental map before it starts calling `list_files`/`search_code`. |
| `Model/Tool/AbstractTool.php` | Shared path resolution: blocks traversal, symlink components, paths outside the workspace, and access to `app/etc/env.php`, `.env`, and `.git`. |
| `Model/Tool/ReadFileTool.php` / `WriteFileTool.php` / `EditFileTool.php` / `DeleteFileTool.php` | Workspace-scoped file operations. `EditFileTool` requires a unique exact match; write, edit, and single-file deletion require approval. `DeleteFileTool` rejects directories, symbolic links, and protected/out-of-workspace paths. |
| `Model/Tool/ListFilesTool.php` / `SearchCodeTool.php` | Read-only exploration tools; no approval needed. `SearchCodeTool` shells out to `grep`. |
| `Model/Tool/TerminalTool.php` | Runs `php -l <file.php>` or `xml-lint <file.xml>` without a shell. XML checks well-formedness, not Magento schema validity. Requires approval. |
| `Model/Tool/GitDiffTool.php` | Read-only `git diff` viewer that omits protected configuration files. |

---

## 3. How this talks to Ollama and qwen-coder

1. **Transport**: `OllamaClient` sends a standard HTTP `POST` to `{OLLAMA_HOST}/api/chat` (Ollama's
   native chat API, not the OpenAI-compatible shim), with `stream: false` — the whole reply comes
   back in one response rather than token-by-token.
2. **JSON-constrained output**: every request sets `"format": "json"`. This tells Ollama to
   constrain qwen-coder's sampling so the reply is always valid JSON — necessary because
   qwen-coder's native tool-calling support isn't reliable enough to depend on through Ollama.
   Instead, the model is instructed (in the system prompt built by `Planner`) to always reply with
   exactly one JSON object:
   ```
   {"thought": "...", "tool": "<tool name>", "args": {...}}      // to call a tool
   {"thought": "...", "final": "<answer>"}                       // to finish
   ```
3. **The loop** (`Agent::run()`):
   - The full conversation (system prompt + everything so far) is sent to Ollama.
   - `Planner::parse()` reads the JSON back (tolerating stray code fences or text around it).
   - If it's a tool call, `ToolExecutor` runs it (with approval if required) and the result is
     appended to the conversation as a new user message (`TOOL RESULT (tool_name): ...`), and the
     loop repeats.
   - If it's `final`, that text is returned as the answer and the loop stops.
   - This repeats for up to 15 steps per task, with older messages trimmed once the conversation
     exceeds 40 messages (agent context defaults to 8,192 tokens; ask mode defaults to 2,048).
4. **Model/host configuration**: defaults are in `etc/config.xml` and can be changed in
   **Stores > Configuration > MageStack > Agent > Ollama**. Environment settings override Admin
   values, and command options override those for the current invocation:
   - `OLLAMA_HOST` — default `http://host.docker.internal:11434` (reaches Ollama on Windows from
     inside the PHP container)
   - `OLLAMA_MODEL` — default `qwen2.5-coder:14b`
   - `AGENT_WORKSPACE` — default the Magento root
    - `OLLAMA_NUM_CTX` — optional agent context override (Admin default: 8,192)
    - `OLLAMA_ASK_NUM_CTX` — optional ask-mode context override (Admin default: 2,048)

---

## 4. Install

For a Composer-published package, add it as a development dependency in the Magento or Mage-OS
project root:

```bash
composer require --dev magestack/module-agent
```

Then enable it in the development environment:

```bash
bin/magento deploy:mode:set developer   # if not already in developer mode
bin/magento module:enable MageStack_Agent
bin/magento setup:upgrade
bin/magento cache:flush
```

Deploy production artifacts with `composer install --no-dev`; this omits Composer `require-dev`
packages. If you use this module directly from `app/code` instead of Composer, ensure your
production build/deployment excludes `app/code/MageStack/Agent` and does not enable
`MageStack_Agent` in the production `app/etc/config.php`. A package cannot force a consuming
project to classify it as `require-dev`, and Magento's developer-mode guard only blocks command
execution; it does not uninstall module files.

The module targets the common `magento/framework` API used by Magento Open Source/Adobe Commerce and
Mage-OS. Validate against the exact platform version in your project during CI.

### Ollama connectivity (Windows host + Docker/WSL)

Set these on the PHP container (e.g. in `docker-compose.yml`):

```yaml
environment:
  OLLAMA_HOST: "http://host.docker.internal:11434"
  OLLAMA_MODEL: "qwen2.5-coder:14b"
extra_hosts:
  - "host.docker.internal:host-gateway"
```

If `host.docker.internal` doesn't resolve (plain docker-ce inside WSL rather than Docker
Desktop), find the Windows host IP from inside WSL:

```bash
cat /etc/resolv.conf | grep nameserver
```

and set `OLLAMA_HOST=http://<that-ip>:11434` instead. Also make sure Ollama itself is reachable
from outside `127.0.0.1` — `OLLAMA_HOST=0.0.0.0` as a **Windows** environment variable (restart
Ollama after setting it), and allow port `11434` through Windows Firewall for private networks.

---

## 5. Example commands

```bash
docker exec -it <php-container> bash

# One-shot task
bin/magento magestack:agent:run "Add a plugin on ProductRepository::save that logs the SKU"

# Plain question: same configured model, no coding-agent tools or project context
bin/magento magestack:agent:ask "What is Magento 2?"

# Override the model or ask-mode context size
bin/magento magestack:agent:ask --model=qwen2.5-coder:14b --num-ctx=1024 "What is Magento 2?"

# Interactive session — keeps conversation history between prompts until you type exit/quit
bin/magento magestack:agent:run

# Auto-approve writes, edits, and PHP syntax checks (use with care)
bin/magento magestack:agent:run --yes "run php -l on app/code/MageStack/Agent/Console/Command/RunCommand.php"

# Use a bigger/smaller model for this run only
bin/magento magestack:agent:run --model=qwen2.5-coder:32b "Explain how the checkout totals collector chain works"

# Point the agent at a different root (rarely needed — defaults to the Magento root)
bin/magento magestack:agent:run --workspace=/var/www/html/app/code/MageStack/Integration "Review this module for missing PHPUnit coverage"
```

Sample interactive session:

```
$ bin/magento magestack:agent:run
mage-agent (qwen2.5-coder:14b) - workspace: /var/www/html
Type a task, or 'exit' to quit.

> Add a bracketed-tag log line to ProductRepository::save on failure

Let me find the class first
> search_code {"pattern":"class ProductRepository","glob":"*.php"}

Reading the save method
> read_file {"path":"vendor/magento/module-catalog/Model/ProductRepository.php","start_line":300,"end_line":340}

I'll add a plugin instead of editing core
> write_file {"path":"app/code/MageStack/Integration/Plugin/ProductRepositorySavePlugin.php","content":"..."}

WriteFileTool wants to run:
{
    "path": "app/code/MageStack/Integration/Plugin/ProductRepositorySavePlugin.php",
    "content": "..."
}
Allow? [y/N] y

Done — added an around plugin that logs failures with the [MageStack][Integration][ProductSave] tag.
```

---

## 6. How to attach context and instructions

Right now, "context" reaching the model comes from three places, all assembled automatically by
`Planner::buildSystemPrompt()` on every run — **you don't repeat anything by hand**:

1. **The base agent instructions** (hard-coded in `Planner`) — tool-use protocol, JSON output
   format, general rules ("explore before you change anything", "prefer `edit_file` over
   `write_file`", etc.).
2. **`MagentoContext`** — Magento edition/version, your custom modules under `app/code`, and a
   fixed block of Magento conventions (DI over ObjectManager, plugins over rewrites, etc.).
3. **`CodebaseContext`** — a quick map of the workspace (top-level folders, file-type counts).

**What's not there yet**: your personal docblock/logging conventions (the ones tracked
separately for your other work — fixed copyright header, class docblock format, `[MageStack][X][Y]`
logger-prefix rule) aren't injected automatically. Two ways to add them:

- **Quick, no code change**: paste your standing conventions into the task text itself, or into
  the first message of an interactive session — the model will follow them for the rest of that
  session (conversation history is kept until you `exit`).
- **Proper fix (recommended)**: add a fourth context source — e.g. an `AGENTS.md` file at the
  Magento root — and a small `StyleContext` class (alongside `MagentoContext`) that reads it if
  present and appends it to the system prompt in `RunCommand`, the same way the other two context
  blocks are wired in. This means you write your conventions once, and every future run —
  one-shot or interactive — includes them automatically, with no per-task repetition. I can build
  this now if you'd like.

---

## 7. Safety model (recap)

- **Workspace paths**: file tools block traversal, symlink components, paths outside the workspace,
  and direct access to `app/etc/env.php`, `.env`, and `.git`. Search also prunes exact ignored
  directories and protected files; git diff excludes protected configuration files.
- **Limited terminal**: the terminal tool accepts only `php -l <file.php>` or `xml-lint <file.xml>`
  and invokes PHP lint without a shell. XML validation checks well-formedness, not Magento schema
  validity. These are application-level restrictions, not an operating-system sandbox.
- **Human approval**: `write_file`, `edit_file` and `terminal` all require a `[y/N]` confirmation
  per call unless `--yes` is passed; if the session isn't interactive and `--yes` wasn't passed,
  those calls are skipped rather than silently denied or hung.
- **Developer mode only**: enforced at the very top of `RunCommand::execute()`, before any tool,
  model call, or workspace resolution happens.
- **Structured logging**: tool failures, LLM failures and bootstrap failures all log to
  `var/log/mage-agent.log` with a `[MageStack][Agent][...]` tag, `error_message`, and
  `getTraceAsString()` — never raw exception objects.

---

## 8. Things worth knowing / possible next steps

- **No automatic AGENTS.md/style loading yet** — see §6. This is the main gap if you want to stop
  repeating your coding-standards preferences.
- **No test coverage for the module itself** yet (`Test/Unit/...`) — since your own conventions
  call for PHPUnit coverage, this module doesn't currently hold itself to that standard.
- **No streaming output** — `stream: false` means you wait for the full model reply each step;
  fine for qwen-coder 14B locally, but worth knowing before trying much larger models.
- **Terminal checks are intentionally narrow** — tests and other project commands must be run
  separately rather than through the agent's terminal tool.
- **Single model, single conversation at a time** — no concurrent sessions, no persistence of
  history across separate `bin/magento` invocations (each one-shot run starts fresh; only
  interactive sessions keep history, and only for that session).