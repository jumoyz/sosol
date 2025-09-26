import React, { useEffect } from 'react';
import { View, Text, Image } from 'react-native';
import { useAuth } from '@/context/AuthContext';

export default function SplashScreen({ navigation }) {
  const { checkAuth } = useAuth();

  useEffect(() => {
    const initializeApp = async () => {
      const isAuthenticated = await checkAuth();
      
      setTimeout(() => {
        if (isAuthenticated) {
          navigation.replace('Home');
        } else {
          navigation.replace('Login');
        }
      }, 2000);
    };

    initializeApp();
  }, []);

  return (
    <View className="flex-1 justify-center items-center bg-primary">
      <Text className="text-4xl font-bold text-white mb-4">SOSOL</Text>
      <Text className="text-lg text-white opacity-90">Beta Version</Text>
    </View>
  );
}