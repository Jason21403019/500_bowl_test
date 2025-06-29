/**
 * SweetAlert2 輔助函數
 * 提供通用的彈窗功能，可在整個專案中重複使用
 */
const UDN_LOGIN_URL = "https://member.udn.com/member/login.jsp"; // 登入頁面URL
const SwalHelper = {
  // 統一的彈窗樣式設定
  defaultSettings: {
    buttonColor: "#000",
    errorButtonColor: "#000",
    customClasses: {
      popup: "swal-custom-popup",
      htmlContainer: "swal-custom-html-container",
      confirmButton: "swal-custom-confirm",
    },
  },
  /**
   * 創建自定義關閉按鈕（完全參照 ActPopup 設計）
   * @param {Function} closeCallback - 關閉回調函數
   * @returns {HTMLElement} - 關閉按鈕元素
   */
  createCustomCloseButton(closeCallback) {
    const closeButton = document.createElement("button");
    closeButton.className = "swal-custom-close-btn";
    closeButton.id = "swal-close-modal";

    // 使用與 ActPopup 相同的 SVG 圖片和樣式
    closeButton.innerHTML = `<img src="https://event.udn.com/bd_500bowls_vote2025/image/close_btn.svg" alt="關閉" style="width: 100%; height: 100%;" />`;

    // 應用與 ActPopup 完全相同的內聯樣式
    closeButton.style.cssText = `
      background: transparent;
      border: none;
      width: 24px;
      height: 24px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      position: absolute;
      top: -20px;
      right: -40px;
      z-index: 1001;
      padding: 0;
    `;

    // 添加點擊事件
    closeButton.addEventListener("click", closeCallback);

    return closeButton;
  },

  /**
   * 複製文本到剪貼板的功能
   * @param {string} text - 要複製的文本
   * @param {HTMLElement} button - 點擊的按鈕元素
   */
  copyToClipboard(text, button) {
    navigator.clipboard
      .writeText(text)
      .then(() => {
        const originalText = button.textContent;
        button.textContent = "已複製";
        button.classList.add("copied");
        setTimeout(() => {
          button.textContent = originalText;
          button.classList.remove("copied");
        }, 2000);
      })
      .catch((err) => {
        console.error("無法複製文字: ", err);
      });
  },

  /**
   * 顯示登入模態框
   */
  showLoginModal() {
    return Swal.fire({
      title: "",
      html: '<div class="swal-custom-content-wrapper"><h2>投票前，請先登入會員</h2><p>你現在即將前往會員中心。<br />成功登入後，即可為心愛的美食，<br />投下寶貴的一票。</p></div>',
      confirmButtonText: "確定 >>",
      confirmButtonColor: this.defaultSettings.buttonColor,
      customClass: {
        ...this.defaultSettings.customClasses,
      },
      didOpen: (popup) => {
        // 添加自定義關閉按鈕
        const closeButton = this.createCustomCloseButton(() => {
          Swal.close();
        });
        popup.appendChild(closeButton);
        const confirmButton = Swal.getConfirmButton();
        confirmButton.setAttribute("data-action", "submit");
        confirmButton.setAttribute("target", "_blank");
      },
    }).then((result) => {
      if (result.isConfirmed) {
        // 在新分頁中打開登入頁面，而不是重定向當前頁面
        const currentUrl = window.location.href.split("#")[0]; // 移除任何現有的錨點
        const loginUrl = `${UDN_LOGIN_URL}?site=bd_500bowls_vote2025&again=y&redirect=${currentUrl}`;
        window.open(loginUrl, "_blank");

        // 設置一個本地存儲標記，供用戶返回時檢查
        localStorage.setItem("shouldScrollToBookHeader", "true");

        return false; // 阻止默認的關閉行為
      }
    });
  },

  /**
   * 顯示 Cloudflare Turnstile 機器人驗證
   * @param {function} callback - 驗證成功後的回調函數
   * @param {string} bookId - 食物ID
   * @returns {Promise} - SweetAlert2 實例
   */
  showCaptchaModal(callback, bookId) {
    // 確保不會有多個驗證對話框
    if (window.captchaModalActive) {
      return Promise.resolve();
    }

    // 標記驗證對話框處於活動狀態
    window.captchaModalActive = true;

    // 先清除任何可能存在的 Turnstile 相關元素
    if (typeof turnstile !== "undefined" && window.turnstileWidgetId) {
      turnstile.reset(window.turnstileWidgetId);
      window.turnstileWidgetId = null;
    }

    // 生成唯一ID防止衝突
    const captchaElementId = `cf-turnstile-${Date.now()}`;

    return Swal.fire({
      title: "",
      html: `
        <div class="swal-custom-content-wrapper swal-captcha-wrapper">
          <h2>機器人驗證</h2>
          <p>投票前，請先完成下方的機器人驗證</p>
          <div class="cf-turnstile" id="${captchaElementId}" data-sitekey="0x4AAAAAAA5howw-D6z-rI8z" data-theme="light"></div>
        </div>
      `,
      showConfirmButton: false,
      allowOutsideClick: false,
      customClass: {
        ...this.defaultSettings.customClasses,
        popup: "swal-custom-popup swal-captcha-popup",
      },
      didOpen: (popup) => {
        // 確保 Cloudflare Turnstile 腳本已加載
        if (typeof turnstile === "undefined") {
          const script = document.createElement("script");
          script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js";
          script.async = true;
          script.defer = true;
          document.head.appendChild(script);

          script.onload = () => {
            this.renderTurnstile(callback, bookId, captchaElementId);
          };
        } else {
          this.renderTurnstile(callback, bookId, captchaElementId);
        }
        // 添加自定義關閉按鈕
        const closeButton = this.createCustomCloseButton(() => {
          Swal.close();
        });
        popup.appendChild(closeButton);
      },
      didDestroy: () => {
        // 清除活動狀態標記
        window.captchaModalActive = false;
      },
      didClose: () => {
        // 確保在對話框關閉時清理驗證元件
        if (typeof turnstile !== "undefined" && window.turnstileWidgetId) {
          turnstile.reset(window.turnstileWidgetId);
          window.turnstileWidgetId = null;
        }
      },
    });
  },

  /**
   * 渲染 Cloudflare Turnstile 驗證元件
   * @param {function} callback - 驗證成功後的回調函數
   * @param {string} bookId - 食物ID
   * @param {string} captchaElementId - Turnstile 元件的唯一ID
   */
  renderTurnstile(callback, bookId, captchaElementId) {
    // 先清除頁面上可能存在的所有 Turnstile 元件
    if (typeof turnstile !== "undefined") {
      // 如果之前有儲存 widget ID，先重置它
      if (window.turnstileWidgetId) {
        try {
          turnstile.reset(window.turnstileWidgetId);
          turnstile.remove(window.turnstileWidgetId);
        } catch (e) {
          // console.log("重置 Turnstile 時出錯:", e);
        }
        window.turnstileWidgetId = null;
      }

      // 檢查所有可能的 turnstile iframes 並移除它們
      document.querySelectorAll('iframe[src*="challenges.cloudflare.com"]').forEach((iframe) => {
        iframe.remove();
      });

      // 清除容器元素內容確保乾淨
      const container = document.getElementById(captchaElementId);
      if (container) {
        container.innerHTML = "";
      }
    }

    // 確保 turnstile 對象存在再進行渲染
    if (typeof turnstile === "undefined") {
      console.error("Turnstile 對象未定義，無法渲染驗證元件");
      return;
    }

    // 給予容器元素載入過程中的視覺回饋
    const container = document.getElementById(captchaElementId);
    if (container) {
      container.innerHTML =
        "<div style='text-align: center; padding: 20px;'>載入驗證元件中...</div>";
    }

    // 確保在顯示新的驗證元件前，頁面上已經沒有其他可見的 Turnstile 元件
    setTimeout(() => {
      try {
        // 初始化 Turnstile 驗證元件
        if (container && container.innerHTML) {
          container.innerHTML = ""; // 清除載入指示器
          window.turnstileWidgetId = turnstile.render(`#${captchaElementId}`, {
            sitekey: "0x4AAAAAAA5howw-D6z-rI8z",
            theme: "light",
            callback: (token) => {
              // console.log("驗證成功，獲得令牌:", token);
              // 立即關閉驗證窗口並呼叫回調
              Swal.close();
              if (typeof callback === "function") {
                callback(bookId, token);
              }
            },
            "expired-callback": () => {
              // console.log("令牌已過期，需要重新驗證");
              // 可選：顯示提醒
              this.showError("驗證已過期，請重新驗證");
              try {
                turnstile.reset(window.turnstileWidgetId);
              } catch (e) {
                // console.log("重置過期驗證時出錯:", e);
              }
            },
            "error-callback": () => {
              // console.log("驗證過程中發生錯誤");
              this.showError("驗證過程中發生錯誤，請重試");
              try {
                turnstile.reset(window.turnstileWidgetId);
              } catch (e) {
                // console.log("重置錯誤驗證時出錯:", e);
              }
            },
          });
          // console.log("Turnstile 驗證元件已渲染，Widget ID:", window.turnstileWidgetId);
        } else {
          console.error("找不到驗證元件容器，無法渲染驗證元件");
        }
      } catch (error) {
        console.error("渲染 Turnstile 驗證元件時出錯:", error);
        this.showError("無法載入機器人驗證，請稍後再試");
      }
    }, 100); // 短暫延遲確保DOM已更新
  },

  /**
   * 顯示投票訊息
   * @param {string} message - 要顯示的訊息
   * @param {boolean} isSuccess - 是否成功
   * @param {object} discountPinData - 投票獲得的折扣碼資料，包含折扣碼和 PIN 碼
   */
  showVoteMessage(message, isSuccess, discountPinData = null) {
    if (isSuccess) {
      // 成功投票消息
      Swal.fire({
        title: "",
        html: `<div class="swal-vote-success-wrapper">
          <img src="./image/popup_title.png" alt="投票標題圖示" class="vs-title-image">
          <p class="vs-title">投票成功！</p>
          <h3 class="vs-prize-title">恭喜獲得 <span class="linepoints">LINE POINTS 5 點</span> 及<br />iPhone 16 等大獎抽獎資格</h3>
          <ul class="vs-info-list">
            <li class="vs-info-item">* LINE 點數兌換序號將於活動結束後派發。</li>
            <li class="vs-info-item">* 請於活動截止前，<br class="brblock" />至【會員中心】設定點數領取資料，<br />
               填妥你的 e-mail 和手機號碼！<br class="brblock" /><a class="vs-info-link" href="https://member.udn.com/member/ShowMember?actiontype=update" target="_blank">前往會員中心 >></a>
            </li>
          </ul>
          <div class="vs-action-buttons">
            <a href="https://event.udn.com/bd_500bowls_vote2025/" class="vs-action-btn">回活動 >></a>
            <a href="https://500times.udn.com/wtimes/cate/123497?utm_source=udn_bd&utm_medium=button&utm_campaign=bd_500bowls_vote2025" class="vs-action-btn" target="_blank">看更多 >></a>
          </div>
        </div>`,
        showConfirmButton: false,
        customClass: {
          ...this.defaultSettings.customClasses,
          popup: "swal-custom-popup swal-vote-success-popup",
        },
        didOpen: (popup) => {
          // 為所有複製按鈕添加事件監聽器
          const copyButtons = popup.querySelectorAll(".vs-copy-btn");
          const self = this; // 保存SwalHelper的引用

          copyButtons.forEach((button) => {
            const targetId = button.getAttribute("data-copy");
            const targetElement = popup.querySelector(`#${targetId}`);

            if (targetElement) {
              button.addEventListener("click", function () {
                self.copyToClipboard(targetElement.textContent, this);
              });
            }
          });
          // 添加自定義關閉按鈕
          const closeButton = this.createCustomCloseButton(() => {
            Swal.close();
          });
          popup.appendChild(closeButton);
        },
      });
    } else if (message === "already_voted") {
      // 已投票提示
      Swal.fire({
        title: "",
        html: `<div class="swal-already-voted-wrapper">
          <h2 class="av-title">你今天已經投票囉</h2>
          <p class="av-subtitle">每天都有一次投票機會！<br />明天再回來支持你喜愛的美食吧！</p>
          <div class="av-action-buttons">
              <a href="https://event.udn.com/bd_500bowls_vote2025/" class="av-action-btn">回活動 >></a>
            <a href="https://500times.udn.com/wtimes/cate/123497?utm_source=udn_bd&utm_medium=button&utm_campaign=bd_500bowls_vote2025" class="av-action-btn" target="_blank">看更多 >></a>
          </div>
        </div>`,
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
          ...this.defaultSettings.customClasses,
          popup: "swal-custom-popup swal-already-voted-popup",
        },
        didOpen: (popup) => {
          // 為所有複製按鈕添加事件監聽器
          const copyButtons = popup.querySelectorAll(".av-copy-btn");
          const self = this; // 保存SwalHelper的引用

          copyButtons.forEach((button) => {
            const targetId = button.getAttribute("data-copy");
            const targetElement = popup.querySelector(`#${targetId}`);

            if (targetElement) {
              button.addEventListener("click", function () {
                self.copyToClipboard(targetElement.textContent, this);
              });
            }
          });
          // 添加自定義關閉按鈕
          const closeButton = this.createCustomCloseButton(() => {
            Swal.close();
          });
          popup.appendChild(closeButton);
        },
      });
    } else {
      // 顯示一般錯誤訊息
      Swal.fire({
        title: "",
        html: `<div class="swal-custom-content-wrapper"><h2>投票失敗</h2><p class="error">${message}</p></div>`,
        icon: "error",
        confirmButtonText: "確定",
        confirmButtonColor: this.defaultSettings.errorButtonColor,
        customClass: this.defaultSettings.customClasses,
      });
    }
  },

  /**
   * 顯示處理中的狀態
   * @param {string} title - 標題
   * @param {string} text - 文本
   * @returns {Promise} - SweetAlert2 實例
   */
  showLoading(title = "處理中...", text = "請稍候") {
    return Swal.fire({
      title: "",
      html: `<div class="swal-custom-content-wrapper-loading"><h2>${title}</h2><p>${text}</p></div>`,
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
      customClass: this.defaultSettings.customClasses,
    });
  },

  /**
   * 顯示一般錯誤訊息
   * @param {string} message - 錯誤訊息
   */
  showError(message) {
    console.error(message);

    return Swal.fire({
      title: "",
      html: `<div class="swal-custom-content-wrapper-loading"><h2>發生錯誤</h2><p class="error">${message}</p></div>`,
      icon: "error",
      confirmButtonText: "確定",
      confirmButtonColor: this.defaultSettings.errorButtonColor,
      customClass: this.defaultSettings.customClasses,
    });
  },

  /**
   * 重定向到登入頁面
   * @param {string} UDN_LOGIN_URL - 登入URL
   */
  redirectToLogin(UDN_LOGIN_URL) {
    const currentUrl = window.location.href;
    const loginUrl = `${UDN_LOGIN_URL}?site=bd_500bowls_vote2025&again=y&redirect=${currentUrl}`;
    window.location.href = loginUrl;
  },

  /**
   * 初始化跳轉到食物頭部的功能
   * 當用戶從登入頁返回時，自動滾動到食物頭部
   */
  initScrollToBookHeader() {
    if (localStorage.getItem("shouldScrollToBookHeader") === "true") {
      // 清除標記避免重複觸發
      localStorage.removeItem("shouldScrollToBookHeader");

      // 延遲一點時間確保頁面已完全載入
      setTimeout(() => {
        const foodHeader = document.getElementById("food-header");
        if (foodHeader) {
          const elementPosition = foodHeader.offsetTop;
          const offsetPosition = elementPosition - 200;
          foodHeader.scrollIntoView({ top: offsetPosition, behavior: "smooth" });

          // 添加高亮效果，讓用戶更容易注意到捲動位置
          foodHeader.classList.add("highlight-element");
          setTimeout(() => {
            foodHeader.classList.remove("highlight-element");
          }, 2000);
        } else {
          // console.log("未找到 food-header 元素");
        }
      }, 1000);
    }
  },
};

// 導出模組，以便在其他檔案中使用
export default SwalHelper;

// 初始化滾動到食物頭部的功能
document.addEventListener("DOMContentLoaded", () => {
  SwalHelper.initScrollToBookHeader();
});
