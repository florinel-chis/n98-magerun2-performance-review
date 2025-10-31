# Performance Review Sub-agents

This directory contains 6 specialized Claude Code sub-agents for working with the Performance Review module.

## What Are Sub-agents?

Sub-agents are specialized AI assistants that Claude Code can delegate tasks to. Each operates with its own context window and specialized expertise, making them more efficient and focused than using the main Claude instance for everything.

## Available Sub-agents

| Sub-agent | Purpose | When Used | File Size |
|-----------|---------|-----------|-----------|
| **analyzer-creator** | Create new custom analyzers | User wants to add performance checks | 16KB |
| **analyzer-debugger** | Debug analyzer issues | Analyzer not loading or not working | 13KB |
| **test-writer** | Write PHPUnit tests | Need test coverage for analyzers | 18KB |
| **code-reviewer** | Review analyzer code quality | Before committing changes | 14KB |
| **documentation-updater** | Update documentation | After code changes | 10KB |
| **performance-optimizer** | Optimize slow analyzers | Analyzer has performance issues | 14KB |

**Total:** 6 agents, ~85KB of specialized expertise

## How They Work

### Automatic Invocation

Claude Code automatically selects the right sub-agent based on your request:

```
"Create a custom analyzer to check Redis memory"
→ analyzer-creator agent automatically invoked

"My analyzer isn't showing up in --list-analyzers"
→ analyzer-debugger agent automatically invoked

"Write tests for my analyzer"
→ test-writer agent automatically invoked
```

### Explicit Invocation

You can also explicitly request a specific agent:

```
"Use the analyzer-creator agent to create a new analyzer"
"Use the code-reviewer agent to review my code"
"Use the performance-optimizer agent to optimize this analyzer"
```

## Sub-agent Details

### 1. analyzer-creator

**Expertise:** Creating new custom analyzers from requirements

**Workflow:**
1. Gathers requirements (what to check, thresholds, priority)
2. Designs analyzer structure (interfaces, dependencies)
3. Implements complete PHP class
4. Creates YAML configuration
5. Provides testing commands
6. Documents usage

**Output:**
- Complete analyzer PHP file
- YAML configuration
- Testing commands
- Usage documentation

**Use when:**
- Adding new performance checks
- Monitoring specific Magento aspects
- Validating custom module behavior

**Keywords that trigger:**
- "create analyzer"
- "add performance check"
- "monitor"
- "custom analysis"

---

### 2. analyzer-debugger

**Expertise:** Diagnosing and fixing analyzer issues

**Workflow:**
1. Gathers context (error messages, when it fails)
2. Verifies registration (--list-analyzers)
3. Checks file structure and syntax
4. Validates YAML configuration
5. Tests with verbose output
6. Provides specific fix

**Output:**
- Diagnostic report
- Root cause analysis
- Specific fix with code
- Verification commands

**Use when:**
- Analyzer not appearing in list
- "Class not found" errors
- Analyzer throwing exceptions
- No issues detected when there should be

**Keywords that trigger:**
- "not working"
- "not loading"
- "not found"
- "throwing errors"
- "debug analyzer"

---

### 3. test-writer

**Expertise:** Writing comprehensive PHPUnit tests

**Workflow:**
1. Reads and understands analyzer
2. Identifies test scenarios
3. Creates test file with multiple test cases
4. Provides mock helpers
5. Includes running instructions

**Output:**
- Complete test class
- Test coverage summary
- Running commands
- Expected results

**Use when:**
- New analyzer created
- Existing analyzer modified
- Need to prevent regressions
- Before committing changes

**Keywords that trigger:**
- "write tests"
- "test coverage"
- "unit tests"

---

### 4. code-reviewer

**Expertise:** Reviewing code quality, performance, and security

**Workflow:**
1. Reads CLAUDE.md for standards
2. Systematically reviews against checklist
3. Categorizes issues (Critical/Important/Minor)
4. Provides fixes with code examples
5. Makes approve/reject recommendation

**Output:**
- Structured review report
- Issues by severity
- Code examples for fixes
- Overall recommendation

**Use when:**
- After writing analyzer
- Before committing
- Before pull requests
- Want quality assurance

**Keywords that trigger:**
- "review code"
- "check quality"
- "code review"

---

### 5. documentation-updater

**Expertise:** Keeping documentation synchronized with code

**Workflow:**
1. Identifies what changed
2. Determines affected documentation files
3. Updates each file appropriately
4. Verifies cross-references
5. Ensures consistency

**Output:**
- List of files updated
- Specific changes made
- Rationale for updates

**Use when:**
- Added new analyzer
- Changed interfaces or patterns
- Fixed bugs
- Any user-visible changes

**Keywords that trigger:**
- "update docs"
- "update documentation"

---

### 6. performance-optimizer

**Expertise:** Optimizing slow or memory-intensive analyzers

**Workflow:**
1. Profiles and measures baseline
2. Identifies bottlenecks
3. Applies appropriate optimization
4. Measures improvement
5. Verifies functionality preserved

**Output:**
- Performance metrics (before/after)
- Optimization strategy
- Optimized code
- Verification results

**Use when:**
- Analyzer takes > 5 seconds
- Memory exhausted errors
- Large installations have issues

**Keywords that trigger:**
- "optimize"
- "slow"
- "memory"
- "performance"

## Usage Examples

### Example 1: Creating a New Analyzer

```
You: "I need to monitor my custom log table size and alert when it exceeds 5GB"

→ analyzer-creator agent invoked

Agent delivers:
- Complete analyzer PHP class
- YAML configuration
- Testing commands
- Documentation

You can then test:
n98-magerun2.phar performance:review --list-analyzers
n98-magerun2.phar performance:review --category=custom
```

### Example 2: Debugging an Analyzer

```
You: "My CustomLogAnalyzer isn't showing up in the list"

→ analyzer-debugger agent invoked

Agent:
1. Checks file exists
2. Validates PHP syntax
3. Reviews YAML config
4. Finds: Missing quotes around class name in YAML
5. Provides fix
6. Gives verification command
```

### Example 3: Complete Workflow

```
1. "Create analyzer to check Redis memory usage"
   → analyzer-creator creates it

2. "Debug why it's not loading"
   → analyzer-debugger fixes YAML issue

3. "Write tests for this analyzer"
   → test-writer creates comprehensive tests

4. "Review the code before I commit"
   → code-reviewer provides quality review

5. "It's too slow, can you optimize?"
   → performance-optimizer improves performance

6. "Update documentation for this new analyzer"
   → documentation-updater synchronizes docs
```

## Benefits

### 1. Specialized Expertise
Each agent is an expert in its domain with focused knowledge.

### 2. Efficiency
Sub-agents work faster than general-purpose assistance.

### 3. Consistency
All agents follow module patterns and best practices.

### 4. Quality
Systematic workflows ensure thorough coverage.

### 5. Integration
Agents suggest next steps and hand off to other agents.

## Integration Between Agents

Sub-agents work together seamlessly:

```
analyzer-creator → test-writer
   "Would you like me to create tests for this analyzer?"

analyzer-debugger → code-reviewer
   "After fixing bugs, use code-reviewer to ensure quality"

code-reviewer → performance-optimizer
   "Found performance issues, use performance-optimizer"

any agent → documentation-updater
   "After changes, use documentation-updater to sync docs"
```

## Customization

You can modify any sub-agent to fit your needs:

1. **Edit the .md file** to change behavior
2. **Adjust model** (sonnet, opus, haiku)
3. **Restrict tools** (only grant necessary ones)
4. **Add team standards** (coding guidelines)
5. **Add project context** (specific requirements)

Example customization:
```markdown
---
name: analyzer-creator
model: haiku  # Change from sonnet to haiku for speed
tools: Read, Write  # Remove Bash if not needed
---

# Your custom instructions here
```

## Best Practices

### For Users

1. **Be specific** - "Create analyzer to check Redis memory" vs "make analyzer"
2. **Use keywords** - Helps trigger the right agent
3. **Follow suggestions** - Agents suggest logical next steps
4. **Trust the workflow** - Agents follow proven processes

### For Teams

1. **Version control** - Check agents into git
2. **Share improvements** - Let team benefit from customizations
3. **Document changes** - Note why you modified agents
4. **Review regularly** - Keep agents up-to-date

## Troubleshooting

### Agent Not Invoked

**Problem:** Claude Code doesn't use the sub-agent

**Solutions:**
- Use explicit invocation: "Use the analyzer-creator agent"
- Use trigger keywords: "create analyzer", "debug", "write tests"
- Check agent file exists in `.claude/agents/`

### Agent Doesn't Have Context

**Problem:** Agent doesn't know about recent changes

**Solutions:**
- Sub-agents read CLAUDE.md for patterns
- Ensure CLAUDE.md is up-to-date
- Provide context in your request

### Want Different Behavior

**Problem:** Agent doesn't work how you want

**Solutions:**
- Edit the agent's .md file
- Add your specific requirements
- Adjust model or tools

## File Structure

```
.claude/agents/
├── README.md (this file)
├── analyzer-creator.md
├── analyzer-debugger.md
├── test-writer.md
├── code-reviewer.md
├── documentation-updater.md
└── performance-optimizer.md
```

## Version Information

- **Created:** 2025-01-31
- **Module Version:** 2.0
- **Claude Code Version:** Latest
- **Last Updated:** 2025-01-31

## Additional Resources

- **CLAUDE.md** - Main reference for all agents
- **SUB_AGENTS_RECOMMENDATION.md** - Original design document
- **README.md** - Module documentation
- **CUSTOM_ANALYZERS.md** - Custom analyzer guide

## Feedback and Improvements

To improve sub-agents:

1. **Test with real tasks** - Use them on actual work
2. **Note issues** - Track what doesn't work well
3. **Edit and iterate** - Modify agents based on experience
4. **Share improvements** - Help the team

## Quick Reference

| I want to... | Use this agent |
|--------------|----------------|
| Create new analyzer | analyzer-creator |
| Fix broken analyzer | analyzer-debugger |
| Add tests | test-writer |
| Review code | code-reviewer |
| Update docs | documentation-updater |
| Speed up analyzer | performance-optimizer |

---

**Ready to use?** Try asking Claude Code to create a custom analyzer and watch the agents in action!
