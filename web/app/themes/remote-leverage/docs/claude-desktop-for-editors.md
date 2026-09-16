# Editing landing pages with Claude Desktop

This guide connects Claude Desktop to the Remote Leverage **staging** site so you can read and
change landing pages by asking, rather than by hand.

You do not need to know WordPress, HTML or CSS to use this. You do need to follow the setup
once, carefully, and you will need about twenty minutes.

Everything here points at **staging** — a private copy of the site. Nothing you do can change
the live site that customers see. That is deliberate: staging is where you try things.

---

## Before you start

Ask Adrián for two things and keep them somewhere safe:

1. Your **username** for the staging site.
2. An **application password** — a long string that looks like
   `abcd EFGH 1234 ijkl MNOP 5678`. This is not your normal login password. It is a separate
   key that only this connection uses, and it can be switched off without touching your
   account.

Treat the application password like a password, because it is one. Do not put it in Slack, a
document, or an email thread.

You will also need:

- **Claude Desktop**, from [claude.ai/download](https://claude.ai/download).
- **Node.js**, from [nodejs.org](https://nodejs.org). Download the version labelled **LTS** and
  click through the installer with the defaults. You will never open this program — Claude uses
  it in the background.

Install both, then restart your computer. Claude Desktop will not see Node until you do.

---

## Setting it up

### 1. Open Claude's configuration file

In Claude Desktop, go to **Settings → Developer → Edit Config**. This opens a folder with a file
called `claude_desktop_config.json`. Open that file in any text editor — TextEdit on a Mac,
Notepad on Windows, both are fine.

If the file is empty, that is normal.

### 2. Paste this in

Replace everything in the file with the block below, then substitute your own username and
application password. Keep the quotation marks.

```json
{
  "mcpServers": {
    "remote-leverage-staging": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://staging.remoteleverage.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "PUT-YOUR-USERNAME-HERE",
        "WP_API_PASSWORD": "PUT YOUR APPLICATION PASSWORD HERE"
      }
    }
  }
}
```

Two things that catch people out:

- The application password **keeps its spaces**. Paste it exactly as you were given it.
- Every quotation mark and comma matters. If Claude later says the config is invalid, paste the
  block again fresh rather than hunting for the typo.

Save the file and **quit Claude Desktop completely** — not just the window. On a Mac use
`Cmd+Q`; on Windows right-click the icon in the system tray and choose Quit. Then reopen it.

### 3. Check it worked

In a new chat, ask:

> List the landing pages on the site.

You should get back a list of page names. If you do, you are set up — skip to *What you can
ask for*.

If Claude says it has no tools available, or the list is empty, stop and send Adrián this
sentence: **"MCP tools are not appearing — can you check the CloudFront header forwarding and
that my agent has the edit_published_pages capability?"** That is a server-side setup step, not
something you did wrong, and it cannot be fixed from your machine.

---

## What you can ask for

Claude has nine tools for this site. You never need to name them — just describe what you want.

**Finding your way around**

> Show me the Athena comparison page and list its sections.

Start here, always. Every page is made of named sections, and Claude needs to see them before it
can change anything. The section names are what you will refer to afterwards.

**Changing copy**

> On the Belay comparison page, change the hero headline to "Hire in 14 days, not 14 weeks".

> The pricing section says $1,200 — it should say $1,400.

**Building a new page from an existing one**

> Make a copy of the Athena comparison page called "Remote Leverage vs Boldly", and swap every
> mention of Athena for Boldly.

**Checking performance**

> How many enquiries came from the hire-va page last month?

---

## Three things worth knowing

### Always read what Claude says it skipped

When Claude changes a page it reports back two lists: what it **applied** and what it
**skipped**. Skipped means it was asked to change something that does not exist on that page —
usually a section name that is slightly off.

A skipped change is not a failure, but it does mean **the page did not change in the way you
asked**. If you see a skipped item, ask Claude to list the page's sections again and try with
the exact name it gives you.

### If Claude warns you about a pattern, forward it

Most of these pages are built from template files that live in the developers' code, not in
WordPress. The first time anyone edits such a page, it quietly stops being linked to that
template.

Your change is saved and works. But it now exists only in the staging database, and it will be
**erased** the next time the site is refreshed — which happens regularly and without warning.

Claude will tell you when this happens, in a message that names a file ending in `.php`. When
you see it, **paste that whole message to Adrián.** It takes him a couple of minutes to make the
change permanent. If you skip this step, your work will disappear and nobody will know why.

### Changing the look of a section

Every section has a **Design** panel in the WordPress editor, with controls for spacing above
and below, background colour, and hiding a section on mobile or desktop.

Use those controls rather than the Custom CSS box underneath them. The controls are limited to
values that match the rest of the site, so they cannot produce something that looks off-brand.
The CSS box is there for when a developer needs it.

One thing the background control does **not** do is change the text colour with it. Putting a
dark background behind a section designed for a light one gives you dark text on a dark
background. Most sections have their own light/dark setting — use that instead.

---

## When something looks broken

**Ask Claude to undo it.** It can put a section back the way it was if you tell it what changed.

**Nothing is permanent and nothing is live.** Staging is a copy. The worst outcome is that
somebody restores it, and the only thing lost is your afternoon.

**If you are unsure whether you are about to break something, ask first.** Claude will explain
what a change will do before doing it if you ask it to.
