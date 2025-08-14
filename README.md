# 🍜 500碗 - 小吃四大天王投票平台

## 📝 專案簡介

本專案為「500碗 - 小吃四大天王」全台網路人氣票選活動平台，由聯合新聞網與500碗合作推出，讓使用者票選全台最受歡迎的小吃店家。

- 前台網址：https://event.udn.com/bd_500bowls_vote2025/
- 後台網址：https://lab-event.udn.com/bd_2025storyawards/admin/

## 🛠️ 技術架構

### 前端

- **框架**：Astro.js 5.5.5
- **樣式**：SCSS (BEM 命名規範)
- **互動**：JavaScript + SweetAlert2
- **輪播**：Splide.js
- **樣式框架**：Tailwind CSS

### 後端

- **環境**：PHP
- **資料庫**：Redis (使用 Predis 客戶端)
- **安全性**：Cloudflare Turnstile (防機器人驗證)
- **認證**：UDN 會員系統整合

## 🔧 主要功能

### 1. 小吃投票系統

- 每日一票投票限制
- 即時投票統計與排行榜
- 前三名特別展示區
- 小吃店家資訊展示

### 2. 會員功能

- UDN 會員登入/驗證
- 投票紀錄查詢
- 會員資料填寫 (email、手機)
- 抽獎資格管理

### 3. 活動獎品

- iPhone 16 等大獎
- LINE Points 點數
- SOTHING 高速手持風扇
- 其他豐富獎品

### 4. 管理後台

- 投票數據統計
- Excel 數據導出
- 即時數據刷新
- 系統管理功能

## 📄 專案結構

```
src/
  ├── API/            # 後端 PHP API
  │   ├── backend.php # 主要後端邏輯
  │   ├── chkmember.php # 會員驗證
  │   └── lib/        # Redis 工具庫
  ├── components/     # Astro 組件
  │   ├── Banner.astro     # 主視覺橫幅
  │   ├── booklist.astro   # 小吃店家列表
  │   ├── topthree.astro   # 前三名展示
  │   ├── Act*.astro       # 活動相關組件
  │   └── Nav.astro        # 導航列
  ├── layouts/        # 頁面佈局
  ├── pages/          # 頁面路由
  │   ├── index.astro      # 首頁
  │   ├── admin.astro      # 管理後台
  │   └── admin_login.astro # 後台登入
  ├── js/             # JavaScript 工具
  └── styles/         # 樣式文件
public/
  └── image/          # 靜態圖片資源
    ├── food/         # 小吃店家圖片 (125張)
    └── food_slider/  # 輪播圖片
```

## 🚀 開發與部署

### 本地開發

1. 安裝依賴：`npm install` 或 `pnpm install`
2. 啟動開發伺服器：`npm run dev` 或 `pnpm dev`
3. 訪問：`http://localhost:4321`

### 建置與部署

1. 建置生產版本：`npm run build` 或 `pnpm build`
2. 預覽建置結果：`npm run preview` 或 `pnpm preview`
3. 部署到伺服器：將 `dist/` 資料夾上傳至主機

## 🎯 活動流程

1. **登入 UDN 會員** - 使用聯合新聞網會員帳號
2. **每天投一票** - 為喜愛的小吃店家投票
3. **填寫會員資料** - 在會員中心完善 email 和手機資訊
4. **坐等開獎** - 等待活動結束後的抽獎結果

## 📦 主要依賴

- **@splidejs/splide**: 輪播功能
- **sweetalert2**: 彈窗提示
- **tailwindcss**: CSS 框架
- **dompurify**: XSS 防護
- **js-cookie**: Cookie 管理

## 👥 聯絡資訊

若有問題或建議，請聯絡專案維護人員。
