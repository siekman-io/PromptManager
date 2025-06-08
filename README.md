# 🪄 promptmgr – Manage your personal AI prompts from Linux CLI or desktop

![screenshot](https://raw.githubusercontent.com/siekman-io/promptmgr/main/promptmgr-preview.png)

**promptmgr** is a flexible Bash-based toolset for power users to manage, store, and quickly use AI prompts for ChatGPT, Claude, Midjourney, and other LLMs.  
Features:
- Store prompts with custom variables and categories
- Desktop selection via Rofi, or terminal selection via fzf
- Paste instantly to clipboard, including variable replacement
- MariaDB/Mysql backend for robust, portable storage
- Fast add/list/delete from CLI or desktop
- Safe: secrets/config in a separate file

---

## ✨ Features

- **Blazing fast**: instant prompt selection and copying to clipboard
- **Support for prompt variables** (e.g., `{{topic}}`)
- **Search and organize**: subcategories, AI platform, and descriptions
- **Full terminal and desktop support**:  
  - *prompt_rofi.sh*: Modern GUI selection via [Rofi](https://github.com/davatorium/rofi)
  - *prompt_fzf.sh*: Terminal-only with [fzf](https://github.com/junegunn/fzf)
  - *prompt_web.php* : Web frontend for your prompts
- **Add/edit with your favorite $EDITOR**
- **Separation of secrets and config**
- **Cross-distro** (works on any Linux with Bash, Rofi/fzf, and MariaDB client)
- **Works on Wayland/X11, macOS (pbcopy), and most major environments**

---

## 🚀 Quickstart

### 1. **Clone & install dependencies**

git clone https://github.com/your-username/promptmgr.git
cd promptmgr
sudo apt install rofi mariadb-client xclip # or: sudo dnf/yum/pacman ... etc.
# Optional for CLI/fzf mode: sudo apt install fzf

create a database and import sql /config/database.sql
and set the credentials in the config files. 

copy the file promptmgr_db.conf into your ~/.ssh/ folder 
and copy the files , with your preferred name into /usr/local/bin 

Good Luck
