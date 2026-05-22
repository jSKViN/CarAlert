# Git 学习指南

## 一、Git 是什么

Git 是一个**版本控制系统**，可以帮助我们：
- 📦 保存代码的每一次修改
- 🔄 撤销错误的修改
- 👥 多人协作开发
- 🌐 在不同电脑之间同步代码

---

## 二、基本概念

### 1. 仓库 (Repository)
代码的存储地点，包含所有历史记录。

### 2. 分支 (Branch)
代码的不同版本线路，就像平行宇宙。
- **master/main**: 主分支，用于生产环境
- **dev**: 开发分支，用于日常开发

### 3. 提交 (Commit)
一次代码修改的保存记录，每个提交都有唯一的 ID。

### 4. 远程仓库 (Remote)
GitHub/GitLab 等平台上的仓库，用于多人共享代码。

---

## 三、常用命令速查

### 基础操作

| 命令 | 作用 | 示例 |
|------|------|------|
| `git status` | 查看当前状态 | `git status` |
| `git add <文件>` | 准备提交 | `git add index.php` |
| `git commit -m "描述"` | 保存修改 | `git commit -m "修复bug"` |
| `git log` | 查看提交历史 | `git log --oneline` |

### 分支操作

| 命令 | 作用 | 示例 |
|------|------|------|
| `git branch` | 查看分支 | `git branch` |
| `git checkout <分支>` | 切换分支 | `git checkout dev` |
| `git merge <分支>` | 合并分支 | `git merge dev` |

### 远程操作

| 命令 | 作用 | 示例 |
|------|------|------|
| `git pull` | 拉取远程代码 | `git pull origin dev` |
| `git push` | 推送到远程 | `git push origin dev` |
| `git remote add` | 添加远程仓库 | `git remote add production d:/xampp/htdocs/CarAlert/.git` |
| `git remote -v` | 查看远程配置 | `git remote -v` |

---

## 四、本项目工作流程

### 环境说明

| 环境 | 路径 | 使用分支 | 用途 |
|------|------|----------|------|
| 开发环境 | `e:\Resilio Sync\dev\CarAlert_dev` | dev | 日常开发、测试 |
| 生产环境 | `d:\xampp\htdocs\CarAlert` | master | 线上运行、正式使用 |

### 完整同步流程

#### 方式一：通过 GitHub 同步（标准流程）

##### 第一步：在开发环境修改代码

```bash
# 1. 确保在 dev 分支
cd e:\Resilio Sync\dev\CarAlert_dev
git checkout dev

# 2. 查看修改状态
git status

# 3. 添加要提交的文件
git add index.php js/blacklist.js pages/

# 4. 提交修改（写清楚做了什么）
git commit -m "fix: 修复路径配置问题"
```

##### 第二步：推送到 GitHub

```bash
# 将本地修改上传到 GitHub
git push origin dev
```

##### 第三步：在生产环境同步

```bash
# 1. 切换到生产环境目录
cd d:\xampp\htdocs\CarAlert

# 2. 切换到 dev 分支拉取最新代码
git checkout dev
git pull origin dev

# 3. 切换回 master 分支
git checkout master

# 4. 合并 dev 分支的修改
git merge dev

# 5. 推送到 GitHub（可选但推荐）
git push origin master
```

#### 方式二：本地直接同步（网络不稳定时推荐）

当 GitHub 网络不稳定时，可以直接在开发环境和生产环境之间同步：

##### 配置本地远程仓库（只需配置一次）

```bash
# 在开发环境添加生产环境作为远程仓库
cd e:\Resilio Sync\dev\CarAlert_dev
git remote add production d:/xampp/htdocs/CarAlert/.git

# 查看配置结果
git remote -v
```

##### 日常同步流程

```bash
# ===== 开发环境 =====
# 1. 提交修改
git add .
git commit -m "fix: 修复问题"

# 2. 直接推送到生产环境（跳过 GitHub）
git push production dev

# ===== 生产环境 =====
# 1. 拉取开发环境的修改
cd d:\xampp\htdocs\CarAlert
git checkout dev
git pull production dev

# 2. 合并到 master 分支
git checkout master
git merge dev
```

##### 稳定后备份到 GitHub

```bash
# 开发环境推送到 GitHub
cd e:\Resilio Sync\dev\CarAlert_dev
git push origin dev

# 生产环境也推送到 GitHub
cd d:\xampp\htdocs\CarAlert
git push origin master
```

---

## 五、常见问题解决

### 问题1：git pull/push 卡住或报错

**现象**：命令执行后长时间没反应，或提示连接失败

**解决**：
```bash
# 检查网络连接
ping github.com

# 如果是 HTTPS 方式，尝试切换到 SSH（需要配置密钥）
git remote set-url origin git@github.com:username/repo.git

# 或者增加超时时间
git config --global http.postBuffer 524288000
```

### 问题2：合并冲突

**现象**：`git merge` 提示 conflict

**解决**：
1. 使用 VS Code 打开冲突文件
2. 手动选择保留哪一方的代码
3. 保存后重新提交：
```bash
git add <冲突文件>
git commit
```

### 问题3：误提交需要撤销

**场景1**：提交后发现错误，还没 push

```bash
# 撤销最后一次提交（保留修改）
git reset --soft HEAD^

# 撤销最后一次提交（丢弃修改）
git reset --hard HEAD^
```

**场景2**：已经 push 到远程

```bash
# 创建新的提交来撤销之前的修改
git revert <commit-id>
git push origin dev
```

### 问题4：index.lock 文件占用

**现象**：提示 `.git/index.lock: File exists`

**解决**：
```bash
# 删除锁文件
rm -f .git/index.lock

# 或者在 Windows PowerShell
Remove-Item -Path .git/index.lock -Force
```

---

## 六、实践练习

### 练习1：修改并提交

1. 修改 `index.php` 添加一个注释
2. 使用 `git status` 查看状态
3. 使用 `git add` 添加文件
4. 使用 `git commit` 提交
5. 使用 `git log --oneline` 查看提交记录

### 练习2：分支切换

1. 使用 `git branch` 查看当前分支
2. 使用 `git checkout master` 切换到主分支
3. 使用 `git checkout dev` 切换回开发分支

### 练习3：同步到生产环境

按照上面的完整流程，将开发环境的修改同步到生产环境。

---

## 七、Git 配置建议

### 设置用户名和邮箱

```bash
git config --global user.name "你的名字"
git config --global user.email "你的邮箱"
```

### 设置别名（简化命令）

```bash
git config --global alias.st status
git config --global alias.ci commit
git config --global alias.co checkout
git config --global alias.br branch
git config --global alias.logg "log --oneline --graph --all"
```

使用时：
```bash
git st    # 代替 git status
git ci -m "描述"    # 代替 git commit -m "描述"
git logg  # 查看漂亮的日志图
```

---

## 八、学习资源

| 资源 | 链接 |
|------|------|
| Git 官方文档 | https://git-scm.com/docs |
| Pro Git 中文版 | https://git-scm.com/book/zh/v2 |
| 廖雪峰 Git 教程 | https://www.liaoxuefeng.com/wiki/896043488029600 |

---

## 九、总结

### 常用命令速查

| 场景 | 命令 |
|------|------|
| 开始工作 | `git checkout dev` → `git pull` |
| 完成修改 | `git add .` → `git commit` → `git push` |
| 部署生产 | `git checkout dev` → `git pull` → `git checkout master` → `git merge dev` |
| 查看远程配置 | `git remote -v` |
| 添加本地远程仓库 | `git remote add production d:/xampp/htdocs/CarAlert/.git` |
| 直接推送到生产环境 | `git push production dev` |

### 两种同步方式对比

| 方式 | 优点 | 缺点 | 适用场景 |
|------|------|------|----------|
| 通过 GitHub | 有远程备份、适合多人协作 | 依赖网络、速度较慢 | 团队协作、代码备份 |
| 本地直接同步 | 速度快、不依赖网络 | 无远程备份 | 网络不稳定时、单人开发 |

**记住**：Git 的核心是保存每一次修改，不要害怕犯错，大部分操作都可以撤销！

---

*文档版本: v1.0*  
*最后更新: 2026-05-22*
