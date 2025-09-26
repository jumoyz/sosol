import React, { useState, useEffect } from 'react';
import { View, Text, ScrollView, TouchableOpacity, FlatList, Alert } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import api from '@/services/api';

export default function TiKaneScreen() {
  const [caisses, setCaisses] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchCaisses();
  }, []);

  const fetchCaisses = async () => {
    try {
      const response = await api.get('/ti-kane/');
      setCaisses(response.data.caisses || []);
    } catch (error) {
      Alert.alert('Error', 'Failed to load caisses');
    } finally {
      setLoading(false);
    }
  };

  const handleContribution = async (caisseId) => {
    try {
      const response = await api.post('/ti-kane/pay.php', {
        caisse_id: caisseId,
        amount: 10, // Example amount
      });
      
      if (response.data.success) {
        Alert.alert('Success', 'Contribution made successfully');
        fetchCaisses(); // Refresh data
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to make contribution');
    }
  };

  const renderCaisseItem = ({ item }) => (
    <View className="bg-card rounded-xl p-4 mb-3 border border-border">
      <View className="flex-row justify-between items-start mb-3">
        <View className="flex-1">
          <Text className="text-lg font-semibold text-text">{item.name}</Text>
          <Text className="text-gray-600">{item.members_count} members</Text>
          <Text className="text-gray-600">Total: ${item.total_amount}</Text>
          <Text className="text-gray-600">Next rotation: {item.next_rotation}</Text>
        </View>
        <View className="bg-accent rounded-full px-3 py-1">
          <Text className="text-white text-sm font-medium">{item.status}</Text>
        </View>
      </View>
      
      <TouchableOpacity
        className="bg-primary rounded-lg py-2"
        onPress={() => handleContribution(item.id)}
      >
        <Text className="text-white text-center font-semibold">Pay Now</Text>
      </TouchableOpacity>
    </View>
  );

  return (
    <View className="flex-1 bg-background">
      <ScrollView className="flex-1 px-4 py-6">
        <Text className="text-2xl font-bold text-text mb-6">Ti Kanè</Text>

        {loading ? (
          <Text className="text-center text-gray-500 py-4">Loading caisses...</Text>
        ) : (
          <FlatList
            data={caisses}
            renderItem={renderCaisseItem}
            keyExtractor={(item) => item.id.toString()}
            ListEmptyComponent={
              <Text className="text-center text-gray-500 py-4">No caisses found</Text>
            }
          />
        )}
      </ScrollView>
    </View>
  );
}