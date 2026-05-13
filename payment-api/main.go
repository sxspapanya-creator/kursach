package main

import (
	"crypto/rand"
	"encoding/base64"
	"fmt"
	"net/http"
	"os"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/jinzhu/now"
	"gorm.io/driver/postgres"
	"gorm.io/gorm"
)

const ProcessingStatusId = 1
const CompletedStatusId = 2
const DeclineStatusId = 3

type Payment struct {
	ID          uint64    `gorm:"primaryKey;autoIncrement" json:"id"`
	Order       string    `gorm:"not null;size:255" json:"order"`
	Amount      float32   `gorm:"not null" json:"amount"`
	Description string    `gorm:"size:255;not null" json:"description"`
	StatusID    uint64    `gorm:"not null;index" json:"status_id"`
	Status      Status    `gorm:"foreignKey:StatusID" json:"status,omitempty"`
	ExpiredAt   time.Time `gorm:"not null" json:"expired_at"`
	CardID      *uint64   `gorm:"index" json:"card_id"`
	Card        Card      `gorm:"foreignKey:CardID" json:"card"`
	Token       string    `gorm:"size:255;not null" json:"token"`
}

type Status struct {
	ID   uint64 `gorm:"primaryKey;autoIncrement" json:"id"`
	Name string `gorm:"size:255;not null" json:"name"`
}

type Card struct {
	ID     uint64    `gorm:"primaryKey;autoIncrement" json:"id"`
	Number string    `gorm:"not null;size:255" json:"number"`
	CVV    uint8     `gorm:"not null;" json:"cvv"`
	Date   time.Time `gorm:"not null" json:"date"`
	Token  string    `gorm:"size:255" json:"token"`
}

type Subscription struct {
	ID        uint64  `gorm:"primaryKey;autoIncrement" json:"id"`
	PaymentID uint64  `gorm:"not null;index" json:"payment_id"`
	Payment   Payment `gorm:"foreignKey:PaymentID" json:"payment"`
}

type PaymentRequest struct {
	Order          string  `json:"order" binding:"required"`
	Amount         float32 `json:"amount" binding:"required"`
	Description    string  `json:"description" binding:"required"`
	IsSubscription *bool   `json:"isSubscription" binding:"required"`
}

type CardData struct {
	CardNumber   string `json:"card"`
	Date         string `json:"date"`
	CVV          int    `json:"cvv"`
	CardToken    string `json:"cardToken"`
	RememberCard bool   `json:"rememberCard"`
}
type PaymentResolveRequest struct {
	CardData   CardData `json:"cardData"`
	OrderToken string   `json:"orderToken" binding:"required"`
}
type PaymentDeclineRequest struct {
	OrderToken string `json:"orderToken" binding:"required"`
}

type SubscriptionResolveRequest struct {
	CardData
	OrderToken string `json:"orderToken" binding:"required"`
}

type SubscriptionDeclineRequest struct {
	OrderToken string `json:"orderToken" binding:"required"`
}

func main() {
	db, err := connectDB()
	if err != nil {
		panic(err)
	}
	initDB(db)
	r := gin.Default()
	api := r.Group("/api/v1")
	payment := api.Group("/payment")
	payment.POST("/", func(c *gin.Context) {
		p := &PaymentRequest{}
		err := c.ShouldBindJSON(p)
		if err != nil {
			fmt.Println(err)
			c.JSON(http.StatusBadRequest, gin.H{
				"error":   "Validation failed",
				"details": err.Error(),
			})
			return
		}
		token, err := secureRandomString(125)
		if err != nil {
			panic(err)
		}
		s := &Status{}
		db.First(s, ProcessingStatusId)
		pay := &Payment{
			Order:       p.Order,
			Amount:      p.Amount,
			Description: p.Description,
			StatusID:    s.ID,
			ExpiredAt:   now.BeginningOfDay(),
			Token:       token,
		}
		if p.IsSubscription != nil && *p.IsSubscription {
			sub := &Subscription{
				Payment: *pay,
			}
			db.Create(sub)
		} else {
			db.Create(pay)
		}
		c.JSON(http.StatusOK, gin.H{
			"orderId":        pay.ID,
			"orderToken":     pay.Token,
			"isSubscription": p.IsSubscription,
		})
	})
	payment.POST("/:id/resolve", func(c *gin.Context) {
		id := c.Param("id")
		p := &PaymentResolveRequest{}
		err := c.ShouldBindJSON(p)
		if err != nil {
			fmt.Println(err)
			c.JSON(http.StatusBadRequest, gin.H{
				"error":   "Validation failed",
				"details": err.Error(),
			})
			return
		}
		pay := &Payment{}
		db.First(pay, id)
		if pay.Token != p.OrderToken {
			c.JSON(http.StatusBadRequest, gin.H{
				"error": "Invalid order token",
			})
			return
		}
		db.Model(pay).Update("status_id", CompletedStatusId)
		c.JSON(http.StatusOK, gin.H{
			"message":   "ok",
			"cardToken": nil,
		})
	})
	payment.POST("/payment/:id/decline", func(c *gin.Context) {
		id := c.Param("id")
		p := &PaymentDeclineRequest{}
		err := c.ShouldBindJSON(p)
		if err != nil {
			fmt.Println(err)
			c.JSON(http.StatusBadRequest, gin.H{
				"error":   "Validation failed",
				"details": err.Error(),
			})
			return
		}
		pay := &Payment{}
		db.First(pay, id)
		if pay.Token != p.OrderToken {
			c.JSON(http.StatusBadRequest, gin.H{
				"error": "Invalid order token",
			})
			return
		}
		db.Model(pay).Update("status", DeclineStatusId)
		c.JSON(http.StatusOK, gin.H{
			"message": "ok",
		})
	})
	payment.POST("/:id/subscription/resolve", func(c *gin.Context) {
		s := &SubscriptionResolveRequest{}
		err := c.ShouldBindJSON(s)
		if err != nil {
			fmt.Println(err)
			c.JSON(http.StatusBadRequest, gin.H{
				"error":   "Validation failed",
				"details": err.Error(),
			})
			return
		}
		c.JSON(http.StatusOK, gin.H{
			"message": "ok",
		})
	})
	payment.POST("/:id/subscription/decline", func(c *gin.Context) {
		s := &SubscriptionDeclineRequest{}
		err := c.ShouldBindJSON(s)
		if err != nil {
			fmt.Println(err)
			c.JSON(http.StatusBadRequest, gin.H{
				"error":   "Validation failed",
				"details": err.Error(),
			})
			return
		}
		c.JSON(http.StatusOK, gin.H{
			"message": "ok",
		})
	})
	err = r.Run(":9714")
	if err != nil {
		fmt.Println(err)
	}
}

func connectDB() (*gorm.DB, error) {
	host := getenvDefault("POSTGRES_HOST", "localhost")
	port := getenvDefault("POSTGRES_PORT", "5432")
	user := getenvDefault("POSTGRES_USER", "postgres")
	password := getenvDefault("POSTGRES_PASSWORD", "postgres")
	dbname := getenvDefault("POSTGRES_DB", "payment")

	dsn := fmt.Sprintf(
		"host=%s user=%s password=%s dbname=%s port=%s sslmode=disable TimeZone=UTC",
		host, user, password, dbname, port,
	)

	return gorm.Open(postgres.Open(dsn), &gorm.Config{})
}

func getenvDefault(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func initDB(db *gorm.DB) {
	err := db.AutoMigrate(&Status{}, &Card{}, &Payment{}, &Subscription{})
	if err != nil {
		panic(err)
	}

	var count int64
	db.Model(&Status{}).Count(&count)
	if count == 0 {
		statuses := []Status{
			{ID: 1, Name: "processing"},
			{ID: 2, Name: "completed"},
			{ID: 3, Name: "decline"},
		}
		if err := db.Create(&statuses).Error; err != nil {
			panic("failed to seed statuses: " + err.Error())
		}
	}
}

func secureRandomString(n int) (string, error) {
	bytes := make([]byte, n)
	if _, err := rand.Read(bytes); err != nil {
		return "", err
	}
	return base64.URLEncoding.EncodeToString(bytes)[:n], nil
}
