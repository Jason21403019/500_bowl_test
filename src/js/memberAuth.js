// 會員驗證與狀態管理全域模組
const API_BASE_URL =
  "https://reading.udn.com/story/act/bd_2024storyawards/API/";

// 檢查是否已初始化，避免重複創建
if (!window.memberAuthInitialized) {
  console.log("初始化 memberAuth 模組...");

  const memberAuth = {
    user: {
      id: "",
      email: "",
      isLoggedIn: false,
      votedBooks: new Set(),
      previousId: "",
    },

    // 請求狀態追蹤
    _isPendingRequest: false,
    _pendingPromise: null,

    async checkLoginStatus() {
      // 如果已經有一個正在進行的請求，則返回該請求的 Promise
      if (this._isPendingRequest && this._pendingPromise) {
        console.log("已有進行中的驗證請求，複用請求");
        return this._pendingPromise;
      }

      // 標記開始新請求
      this._isPendingRequest = true;

      // 創建新的驗證請求，並存儲 Promise
      this._pendingPromise = (async () => {
        try {
          this.user.previousId = this.user.id;
          console.log("發送會員驗證請求...");
          const response = await fetch(`${API_BASE_URL}chkmember.php?json=Y`, {
            method: "GET",
            credentials: "include",
          });
          if (!response.ok)
            throw new Error(
              `驗證請求失敗: ${response.status} ${response.statusText}`
            );
          const data = await response.json();
          if (data && data.response && data.response.status === "success") {
            const newUserId = data.response.udnmember || "";
            if (this.user.id && this.user.id !== newUserId) {
              this.user.votedBooks.clear();
            }
            this.user.id = newUserId;
            this.user.email = data.response.email || "";
            this.user.isLoggedIn = true;
          } else {
            this.user.id = "";
            this.user.email = "";
            this.user.isLoggedIn = false;
            this.user.votedBooks.clear();
          }
          return this.user;
        } catch (error) {
          console.error("會員驗證出錯:", error);
          this.user.id = "";
          this.user.email = "";
          this.user.isLoggedIn = false;
          this.user.votedBooks.clear();
          return this.user;
        } finally {
          // 請求完成後重置狀態
          this._isPendingRequest = false;
        }
      })();

      return this._pendingPromise;
    },

    getUser() {
      return this.user;
    },
    // 可擴充更多會員相關方法
  };

  // 設定全域變數並標記為已初始化
  window.memberAuth = memberAuth;
  window.memberAuthInitialized = true;
  console.log("memberAuth 模組初始化完成");
} else {
  console.log("memberAuth 模組已經初始化，重複導入");
}

// 仍然導出 memberAuth 以兼容現有導入語法
export default window.memberAuth;
