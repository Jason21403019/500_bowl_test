/**
 * SweetAlert2 輔助函數
 * 提供通用的彈窗功能，可在整個專案中重複使用
 */
const UDN_LOGIN_URL = "https://member.udn.com/member/login.jsp"; // 登入頁面URL
const SwalHelper = {
  // 統一的彈窗樣式設定
  defaultSettings: {
    buttonColor: "#f37562",
    errorButtonColor: "#f37562",
    customClasses: {
      popup: "swal-custom-popup",
      htmlContainer: "swal-custom-html-container",
      confirmButton: "swal-custom-confirm",
    },
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
      html: '<div class="swal-custom-content-wrapper"><h2>投票前，請先登入會員</h2><p>你現在將前往會員中心。成功登入後，即可為喜愛的作品，投下寶貴的一票。</p></div>',
      confirmButtonText: "確定 »",
      confirmButtonColor: this.defaultSettings.buttonColor,
      showCloseButton: false,
      customClass: {
        ...this.defaultSettings.customClasses,
        closeButton: "swal-custom-close",
      },
      didOpen: () => {
        const confirmButton = Swal.getConfirmButton();
        confirmButton.setAttribute("data-action", "submit");
        confirmButton.setAttribute("target", "_blank");
      },
    }).then((result) => {
      if (result.isConfirmed) {
        // 在新分頁中打開登入頁面，而不是重定向當前頁面
        const currentUrl = window.location.href.split("#")[0]; // 移除任何現有的錨點
        const loginUrl = `${UDN_LOGIN_URL}?site=bd_2024storyawards&again=y&redirect=${currentUrl}`;
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
   * @param {string} bookId - 書籍ID
   * @returns {Promise} - SweetAlert2 實例
   */
  showCaptchaModal(callback, bookId) {
    // 確保不會有多個驗證對話框
    if (window.captchaModalActive) {
      console.log("已有驗證對話框處於活動狀態");
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
      showCloseButton: true,
      allowOutsideClick: false,
      customClass: {
        ...this.defaultSettings.customClasses,
        closeButton: "swal-custom-close",
        popup: "swal-custom-popup swal-captcha-popup",
      },
      didOpen: () => {
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
   * @param {string} bookId - 書籍ID
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
          console.log("重置 Turnstile 時出錯:", e);
        }
        window.turnstileWidgetId = null;
      }

      // 檢查所有可能的 turnstile iframes 並移除它們
      document
        .querySelectorAll('iframe[src*="challenges.cloudflare.com"]')
        .forEach((iframe) => {
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
              console.log("驗證成功，獲得令牌:", token);
              // 立即關閉驗證窗口並呼叫回調
              Swal.close();
              if (typeof callback === "function") {
                callback(bookId, token);
              }
            },
            "expired-callback": () => {
              console.log("令牌已過期，需要重新驗證");
              // 可選：顯示提醒
              this.showError("驗證已過期，請重新驗證");
              try {
                turnstile.reset(window.turnstileWidgetId);
              } catch (e) {
                console.log("重置過期驗證時出錯:", e);
              }
            },
            "error-callback": () => {
              console.log("驗證過程中發生錯誤");
              this.showError("驗證過程中發生錯誤，請重試");
              try {
                turnstile.reset(window.turnstileWidgetId);
              } catch (e) {
                console.log("重置錯誤驗證時出錯:", e);
              }
            },
          });
          console.log(
            "Turnstile 驗證元件已渲染，Widget ID:",
            window.turnstileWidgetId
          );
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
    console.log(`顯示投票消息: "${message}", 是否成功: ${isSuccess}`);
    console.log("折扣碼資料:", discountPinData);

    // 檢查折扣碼資料是否有效
    let hasValidDiscountPin = false;
    let discountCode = "ＸＸＸＸＸＸＸＸＸＸＸＸＸＸＸ";
    let pinCode = "ＸＸＸＸＸＸＸＸＸＸＸＸＸＸＸ";

    // 處理不同格式的折扣碼資料
    if (
      discountPinData &&
      discountPinData !== "null" &&
      discountPinData !== undefined
    ) {
      // 處理折扣碼資料在 discount_pin_data 欄位的情況
      if (discountPinData.discount_pin_data) {
        const innerData = discountPinData.discount_pin_data;
        if (innerData && innerData.discount_code && innerData.pin_code) {
          discountCode = innerData.discount_code;
          pinCode = innerData.pin_code;
          hasValidDiscountPin = true;
        }
      }
      // 直接在 discountPinData 中的情況
      else if (discountPinData.discount_code && discountPinData.pin_code) {
        discountCode = discountPinData.discount_code;
        pinCode = discountPinData.pin_code;
        hasValidDiscountPin = true;
      }

      console.log("處理後的折扣碼資料:", {
        hasValidDiscountPin,
        discountCode,
        pinCode,
      });
    }

    if (isSuccess) {
      // 成功投票消息
      Swal.fire({
        title: "",
        html: `<div class="swal-vote-success-wrapper">
          <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="195.657" height="209.604" viewBox="0 0 195.657 209.604" style="max-width:115px; max-height:130px; margin:0 auto; display:block;">
        <defs>
          <linearGradient id="linear-gradient" x1="0.5" x2="0.5" y2="1" gradientUnits="objectBoundingBox">
            <stop offset="0" stop-color="#fff"/>
            <stop offset="0.049" stop-color="#007eeb"/>
            <stop offset="1" stop-color="#007eeb" stop-opacity="0.502"/>
          </linearGradient>
          <filter id="path_7240" x="0" y="0" width="195.657" height="209.604" filterUnits="userSpaceOnUse">
            <feOffset dy="20" input="SourceAlpha"/>
            <feGaussianBlur stdDeviation="10" result="blur"/>
            <feFlood flood-opacity="0.102"/>
            <feComposite operator="in" in2="blur"/>
            <feComposite in="SourceGraphic"/>
          </filter>
        </defs>
        <g transform="matrix(1, 0, 0, 1, 0, 0)" filter="url(#path_7240)">
          <path id="path_7240-2" data-name="path 7240" d="M92.3,0H22.231A22.256,22.256,0,0,0,0,22.231V81.723a22.256,22.256,0,0,0,22.231,22.231H46.088l25.864,25.863a6.483,6.483,0,0,0,11.068-4.584V103.953H92.3a22.255,22.255,0,0,0,22.229-22.231V22.231A22.255,22.255,0,0,0,92.3,0m-9.73,42.4L54.514,70.455a4.3,4.3,0,0,1-6.083,0L31.673,53.7a4.3,4.3,0,0,1,6.084-6.084L51.472,61.33l25.01-25.01A4.3,4.3,0,1,1,82.566,42.4" transform="matrix(0.98, 0.17, -0.17, 0.98, 52.87, 10)" fill="url(#linear-gradient)"/>
        </g>
          </svg>
          <p class="vs-title">投票成功！${
            hasValidDiscountPin ? "恭喜獲得" : ""
          }</p>
          <h3 class="vs-prize-title">登機箱、閱讀器及禮券抽獎資格<br class="mobile-only">U 利點數 5 點<span class="vs-guide-wrapper">｜</span><a href="https://upoints.udn.com/upt/Point.do?utm_source=udn_bd&utm_medium=content&utm_campaign=bd_2024storyawards" class="vs-guide-link" target="_blank">使用說明</a></h3>
          ${
            hasValidDiscountPin
              ? `
          <div class="vs-code-container">
            <div class="vs-code-row">
              <div class="vs-code-info">
                <span class="vs-star-icon">✦</span> <span class="vs-code-label">兌換卡號：</span>
                <span class="vs-code-value" id="discount-code">${discountCode}</span>
              </div>
              <button class="vs-copy-btn" data-copy="discount-code">複製</button>
            </div>
            <div class="vs-code-row">
              <div class="vs-code-info">
                <span class="vs-star-icon">✦</span> <span class="vs-code-label">PIN 碼\u3000：</span>
                <span class="vs-code-value" id="pin-code">${pinCode}</span>
              </div>
              <button class="vs-copy-btn" data-copy="pin-code">複製</button>
            </div>
          </div>
                    <p class="vs-exchange-time">兌換時間至 6/30 止，兌換後 14 天內使用完畢</p>

          `
              : ""
          }
          <div class="vs-action-buttons">
            <a href="https://reading.udn.com/story/act/2024storyawards/?utm_source=udn_bd&utm_medium=button&utm_campaign=bd_2024storyawards" class="vs-action-btn" target="_blank">去大賞官網 »</a>
            <a href="https://reading.udn.com/story/?utm_source=udn_bd&utm_medium=button&utm_campaign=bd_2024storyawards" class="vs-action-btn" target="_blank">看更多故事 »</a>
          </div>
        </div>`,
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
          ...this.defaultSettings.customClasses,
          closeButton: "swal-custom-close",
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
        },
      });
    } else if (message === "already_voted") {
      // 已投票提示
      Swal.fire({
        title: "",
        html: `<div class="swal-already-voted-wrapper">
          <h2 class="av-title">你今天已經投票囉</h2>
          <p class="av-subtitle">每天都有一次投票機會！明天再回來支持你喜愛的作品吧！</p>
          ${
            hasValidDiscountPin
              ? `
          <h3 class="av-prize-title">人人有獎 - <br class="mobile-only">U 利點數 5 點<span class="av-guide-wrapper">｜</span><a href="https://upoints.udn.com/upt/Point.do?utm_source=udn_bd&utm_medium=content&utm_campaign=bd_2024storyawards" class="av-guide-link" target="_blank">使用說明</a></h3>
          <div class="av-code-container">
            <div class="av-code-row">
              <div class="av-code-info">
                <span class="av-star-icon">✦</span> <span class="av-code-label">兌換卡號：</span>
                <span class="av-code-value" id="discount-code">${discountCode}</span>
              </div>
              <button class="av-copy-btn" data-copy="discount-code">複製</button>
            </div>
            <div class="av-code-row">
              <div class="av-code-info">
                <span class="av-star-icon">✦</span> <span class="av-code-label">PIN 碼\u3000：</span>
                <span class="av-code-value" id="pin-code">${pinCode}</span>
              </div>
              <button class="av-copy-btn" data-copy="pin-code">複製</button>
            </div>
          </div>
                    <p class="av-exchange-time">兌換時間至 6/30 止，兌換後 14 天內使用完畢</p>

          `
              : ""
          }
          <div class="av-action-buttons">
            <a href="https://reading.udn.com/story/act/2024storyawards/?utm_source=udn_bd&utm_medium=button&utm_campaign=bd_2024storyawards" class="av-action-btn" target="_blank">去大賞官網 »</a>
            <a href="https://reading.udn.com/story/?utm_source=udn_bd&utm_medium=button&utm_campaign=bd_2024storyawards" class="av-action-btn" target="_blank">看更多故事 »</a>
          </div>
        </div>`,
        showConfirmButton: false,
        showCloseButton: true,
        customClass: {
          ...this.defaultSettings.customClasses,
          closeButton: "swal-custom-close",
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
        },
      });
    } else {
      // 顯示一般錯誤訊息
      Swal.fire({
        title: "",
        html: `<div class="swal-custom-content-wrapper"><h2>投票失敗</h2><p>${message}</p></div>`,
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
      html: `<div class="swal-custom-content-wrapper"><h2>${title}</h2><p>${text}</p></div>`,
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
      html: `<div class="swal-custom-content-wrapper"><h2>發生錯誤</h2><p>${message}</p></div>`,
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
    const loginUrl = `${UDN_LOGIN_URL}?site=bd_2024storyawards&again=y&redirect=${currentUrl}`;
    window.location.href = loginUrl;
  },

  /**
   * 初始化跳轉到書籍頭部的功能
   * 當用戶從登入頁返回時，自動滾動到書籍頭部
   */
  initScrollToBookHeader() {
    if (localStorage.getItem("shouldScrollToBookHeader") === "true") {
      // 清除標記避免重複觸發
      localStorage.removeItem("shouldScrollToBookHeader");

      // 延遲一點時間確保頁面已完全載入
      setTimeout(() => {
        const bookHeader = document.getElementById("book-header");
        if (bookHeader) {
          console.log("找到 book-header 元素，進行捲動");
          bookHeader.scrollIntoView({behavior: "smooth"});

          // 添加高亮效果，讓用戶更容易注意到捲動位置
          bookHeader.classList.add("highlight-element");
          setTimeout(() => {
            bookHeader.classList.remove("highlight-element");
          }, 2000);
        } else {
          console.log("未找到 book-header 元素");
        }
      }, 1000);
    }
  },
};

// 導出模組，以便在其他檔案中使用
export default SwalHelper;

// 初始化滾動到書籍頭部的功能
document.addEventListener("DOMContentLoaded", () => {
  SwalHelper.initScrollToBookHeader();
});
