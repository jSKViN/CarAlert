# Git 工作流程

## 📋 目录
- [日常开发流程](#日常开发流程)
- [发布上线流程](#发布上线流程)
- [分支说明](#分支说明)
- [远程仓库说明](#远程仓库说明)

---

## 🛠️ 日常开发流程

> 适用于：功能开发中、代码未完成、仅需备份

```bash
# 1. 确保在 dev 分支
git checkout dev

# 2. 拉取最新代码（防止冲突）
git pull origin dev

# 3. 编写代码...

# 4. 提交代码
git add .
git commit -m "feat: 描述你的修改"

# 5. 推送到 GitHub（备份）
git push origin dev
```

**提示**：这一步可以频繁执行，随时保存进度！

---

## 🚀 发布上线流程

> 适用于：功能完成、测试通过、准备部署到生产环境

```bash
# 1. 切换到 master 分支
git checkout master

# 2. 拉取最新代码
git pull origin master

# 3. 合并 dev 到 master
git merge dev

# 4. 推送到 GitHub
git push origin master

# 5. 部署到生产环境
git push production master

# 6. 切回 dev 继续开发
git checkout dev
```

---

## 🌿 分支说明

| 分支 | 用途 | 说明 |
|------|------|------|
| `dev` | 开发分支 | 日常开发在此分支进行 |
| `master` | 主分支 | 生产环境代码，只在发布时更新 |

---

## 📦 远程仓库说明

| 名称 | 地址 | 用途 |
|------|------|------|
| `origin` | GitHub | 中央代码仓库，用于备份和协作 |
| `production` | `d:\xampp\htdocs\CarAlert` | 生产环境部署目录 |

---

## 💡 最佳实践

### Commit 信息规范
- `feat: 新功能`
- `fix: 修复 bug`
- `docs: 文档更新`
- `style: 代码格式调整`
- `refactor: 重构`
- `test: 测试相关`
- `chore: 构建/工具相关`

### 常见问题
- **忘记切换分支**：用 `git status` 查看当前分支
- **合并冲突**：先解决冲突，再 `git add` 然后 `git commit`
- **推送被拒**：先 `git pull` 拉取最新代码，解决冲突后再推送

---

## 📝 快速参考

```bash
# 查看当前状态
git status

# 查看分支
git branch

# 查看提交历史
git log --oneline --graph --decorate

# 查看远程仓库
git remote -v
```
