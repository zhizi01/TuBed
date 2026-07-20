# TuBed 后台设计系统

本文件是 TuBed 前端视觉与交互的统一约束。页面级规则若存在，可覆盖本文件；未声明部分继续遵守本文件。

## 技术栈

- Vue 3 Composition API
- Pinia
- Vue Router
- Element Plus
- Lucide Vue Next
- ECharts

## 设计方向

- Linear / Notion 式现代 SaaS 后台
- 轻量、克制、低噪音，信息结构优先于装饰
- 中高信息密度，但保持稳定留白
- 卡片圆角柔和，边框低对比，阴影仅用于浮层
- 禁止 Emoji 图标，统一使用 Lucide

## 色彩与主题

- 亮色主强调色：`#5e6ad2`
- 暗色主强调色：`#8b8ffe`
- 语义色只用于成功、危险、警告和信息状态
- 亮色与暗色均使用语义变量，禁止在业务页面硬编码主题色
- 暗色模式由 `html[data-theme="dark"]` 和 Element Plus `dark` class 同步驱动

核心变量：

```css
:root {
  --accent: #5e6ad2;
  --page-bg: #f7f8fa;
  --panel-bg: #ffffff;
  --sidebar-bg: #f3f4f7;
  --text-primary: #17181c;
  --text-secondary: #5f6470;
  --text-tertiary: #8b909b;
  --border: #e5e7ec;
  --manage-max-width: 1440px;
}

html[data-theme='dark'] {
  --accent: #8b8ffe;
  --page-bg: #111216;
  --panel-bg: #18191f;
  --sidebar-bg: #14151a;
  --text-primary: #f2f3f5;
  --text-secondary: #b0b4be;
  --text-tertiary: #7f8490;
  --border: #2a2c34;
}
```

## 布局

- 桌面端固定侧边栏，右侧内容区独立滚动
- 移动端使用顶部栏与抽屉式导航
- 标准内容外边距 32px；移动端缩减为 16px
- 普通页面受 `--manage-max-width` 限制，表格与资源管理页可使用宽阔模式
- 页面必须在 375、768、1024、1440 像素宽度下可用，不能出现横向滚动

## 页面结构

管理类页面统一使用：

```text
manage-page
├── manage-header
├── manage-toolbar
├── manage-table-card
├── manage-pagination
└── el-dialog
```

- 资源类页面优先使用卡片网格
- 管理类页面优先使用表格卡片
- 个人资料页使用 Banner + 信息卡片
- 异步内容必须有 Skeleton 或 Spinner
- 危险操作必须二次确认

## 权限

权限必须三层联动：

1. 路由通过 `meta.permission` 拦截
2. 侧边栏按权限过滤菜单
3. 操作按钮通过 `v-permission` 控制

前端权限只负责体验，后端中间件和控制器必须再次校验。

## 交互与可访问性

- 所有可点击元素显示 pointer 光标
- hover 不允许缩放造成布局抖动
- 动画时长 150–300ms，并支持 `prefers-reduced-motion`
- 键盘 Tab 顺序与视觉顺序一致
- 所有焦点状态清晰可见
- 输入控件具有可感知标签
- 文字对比度不低于 WCAG AA
- 导航密集页面提供“跳至主内容”链接
